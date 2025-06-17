<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\PostPublication;
use App\Entity\Destination;
use App\Repository\DestinationRepository;
use Doctrine\ORM\EntityManagerInterface;

class PublicationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DestinationRepository $destinationRepository,
        private RedditApiService $redditApi
    ) {}

    /**
     * Créer les publications pour toutes les destinations actives de l'utilisateur
     */
    public function createPublicationsForAllDestinations(Post $post): array
    {
        $destinations = $this->destinationRepository->findBy([
            'user' => $post->getUser(),
            'isActive' => true
        ]);

        $publications = [];

        foreach ($destinations as $destination) {
            $publication = $this->createPublicationForDestination($post, $destination);
            $publications[] = $publication;
        }

        $this->entityManager->flush();

        return $publications;
    }

    /**
     * Créer les publications pour des destinations spécifiques
     */
    public function createPublicationsForDestinations(Post $post, array $destinationIds): array
    {
        $destinations = $this->destinationRepository->findBy([
            'id' => $destinationIds,
            'user' => $post->getUser(),
            'isActive' => true
        ]);

        $publications = [];

        foreach ($destinations as $destination) {
            $publication = $this->createPublicationForDestination($post, $destination);
            $publications[] = $publication;
        }

        $this->entityManager->flush();

        return $publications;
    }

    private function createPublicationForDestination(Post $post, Destination $destination): PostPublication
    {
        $publication = new PostPublication();
        $publication->setPost($post);
        $publication->setSocialAccount($destination->getSocialAccount());
        $publication->setDestination($destination->getName());
        $publication->setStatus('pending');

        // Adapter le contenu selon la plateforme
        $this->adaptContentForPlatform($publication, $destination);

        // Appliquer les paramètres de la destination
        if ($destination->getSettings()) {
            $publication->setDestinationSettings($destination->getSettings());
        }

        $this->entityManager->persist($publication);
        
        return $publication;
    }

    /**
     * Récupérer la destination pour une publication
     */
    private function getDestinationForPublication(PostPublication $publication): Destination
    {
        $destination = $this->destinationRepository->findOneBy([
            'name' => $publication->getDestination(),
            'user' => $publication->getPost()->getUser(),
            'socialAccount' => $publication->getSocialAccount()
        ]);

        if (!$destination) {
            throw new \RuntimeException(
                sprintf(
                    'Destination "%s" introuvable pour la publication ID %d',
                    $publication->getDestination(),
                    $publication->getId()
                )
            );
        }

        return $destination;
    }

    private function adaptContentForPlatform(PostPublication $publication, Destination $destination): void
    {
        $platform = $destination->getSocialAccount()->getPlatform();
        $originalTitle = $publication->getPost()->getTitle();
        $originalContent = $publication->getPost()->getContent();

        switch ($platform) {
            case 'reddit':
                // Reddit : titre + contenu
                $publication->setAdaptedTitle($originalTitle);
                $publication->setAdaptedContent($originalContent);
                break;

            case 'twitter':
                // Twitter : tout en un seul tweet (280 chars max)
                $combined = $originalTitle . "\n\n" . $originalContent;
                if (strlen($combined) > 280) {
                    $combined = substr($combined, 0, 277) . '...';
                }
                $publication->setAdaptedContent($combined);
                break;

            default:
                $publication->setAdaptedTitle($originalTitle);
                $publication->setAdaptedContent($originalContent);
                break;
        }
    }

    /**
     * Publier toutes les publications en attente
     */
    public function publishPendingPublications(): array
    {
        $publications = $this->entityManager->getRepository(PostPublication::class)
            ->findBy(['status' => 'pending']);

        $results = [];

        foreach ($publications as $publication) {
            $result = $this->publishSinglePublication($publication);
            $results[] = $result;
        }

        $this->entityManager->flush();

        return $results;
    }

    /**
     * 🔥 FIX : Publier une publication spécifique avec gestion d'erreurs améliorée
     */
    public function publishSinglePublication(PostPublication $publication): array
    {
        try {
            // ✅ VALIDATION BASIQUE
            if (empty($publication->getAdaptedTitle()) && empty($publication->getAdaptedContent())) {
                $publication->setStatus('failed');
                $publication->setErrorMessage('Contenu vide');
                return ['success' => false, 'error' => 'Contenu vide'];
            }

            // ✅ VÉRIFICATION TOKEN
            $socialAccount = $publication->getSocialAccount();
            if (!$socialAccount->isTokenValid()) {
                $publication->setStatus('failed');
                $publication->setErrorMessage('Token invalide ou expiré');
                return ['success' => false, 'error' => 'Token invalide ou expiré'];
            }

            $platform = $socialAccount->getPlatform();
            
            switch ($platform) {
                case 'reddit':
                    return $this->publishToReddit($publication);
                    
                case 'twitter':
                    return $this->publishToTwitter($publication);
                    
                default:
                    throw new \InvalidArgumentException("Plateforme non supportée: {$platform}");
            }
            
        } catch (\Exception $e) {
            error_log("❌ Erreur publication ID {$publication->getId()}: " . $e->getMessage());
            
            $publication->setStatus('failed');
            $publication->setErrorMessage($e->getMessage());
            $publication->incrementRetryCount();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * 🔥 FIX : Publication Reddit avec gestion d'erreurs complète
     */
    private function publishToReddit(PostPublication $publication): array
    {
        try {
            error_log("🚀 Publication Reddit - Début pour publication ID: " . $publication->getId());
            
            // ✅ EXTRAIRE le subreddit du nom de destination
            $destinationName = $publication->getDestination(); // Ex: "r/test"
            $subreddit = str_replace('r/', '', $destinationName); // Ex: "test"
            
            error_log("📍 Destination: {$destinationName}, Subreddit: {$subreddit}");
            error_log("📝 Titre: " . $publication->getAdaptedTitle());
            error_log("📄 Contenu: " . substr($publication->getAdaptedContent(), 0, 100) . "...");
            
            // ✅ APPEL API REDDIT
            $result = $this->redditApi->submitPost(
                $publication->getAdaptedTitle(),
                $publication->getAdaptedContent(),
                $subreddit, // IMPORTANT : passer juste le nom du subreddit, pas "r/test"
                $publication->getSocialAccount()
            );
            
            error_log("✅ Réponse Reddit: " . json_encode($result));
            
            // ✅ TRAITEMENT RÉPONSE
            if (isset($result['json']['errors']) && !empty($result['json']['errors'])) {
                // Reddit a retourné des erreurs
                $errors = $result['json']['errors'];
                $errorMessage = 'Erreurs Reddit: ' . json_encode($errors);
                
                $publication->setStatus('failed');
                $publication->setErrorMessage($errorMessage);
                $publication->setPlatformResponse($result);
                
                error_log("❌ Erreurs Reddit: " . $errorMessage);
                
                return ['success' => false, 'error' => $errorMessage];
            }
            
            // ✅ SUCCÈS
            $postData = $result['json']['data'] ?? null;
            $platformUrl = null;
            $platformPostId = null;
            
            if ($postData) {
                $platformPostId = $postData['name'] ?? $postData['id'] ?? null;
                $platformUrl = $postData['url'] ?? null;
                
                // Construire l'URL si pas fournie
                if (!$platformUrl && isset($postData['permalink'])) {
                    $platformUrl = 'https://reddit.com' . $postData['permalink'];
                }
            }
            
            $publication->markAsPublished(
                $platformPostId ?: 'unknown',
                $platformUrl ?: '',
                $result
            );
            
            error_log("✅ Publication Reddit réussie - URL: " . ($platformUrl ?: 'N/A'));
            
            return [
                'success' => true, 
                'url' => $platformUrl,
                'platform_id' => $platformPostId
            ];
            
        } catch (\Exception $e) {
            error_log("❌ Exception Reddit: " . $e->getMessage());
            error_log("❌ Stack trace: " . $e->getTraceAsString());
            
            $publication->setStatus('failed');
            $publication->setErrorMessage('Erreur API Reddit: ' . $e->getMessage());
            $publication->incrementRetryCount();
            
            return ['success' => false, 'error' => 'Erreur API Reddit: ' . $e->getMessage()];
        }
    }

    private function publishToTwitter(PostPublication $publication): array
    {
        // TODO: Implémenter l'API Twitter
        throw new \Exception('API Twitter pas encore implémentée');
    }
}