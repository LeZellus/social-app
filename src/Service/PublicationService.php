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
        // Ajouter d'autres services API au besoin
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
     * MÉTHODE MANQUANTE : Récupérer la destination pour une publication
     */
    private function getDestinationForPublication(PostPublication $publication): Destination
    {
        // Récupérer la destination via le nom stocké dans la publication
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
     * Publier une publication spécifique
     */
    public function publishSinglePublication(PostPublication $publication): array
    {
        try {
            // ✅ VALIDATION BASIQUE (on peut ajouter PostValidationService plus tard)
            $destination = $this->getDestinationForPublication($publication);
            
            // Validation simple pour l'instant
            if (empty($publication->getAdaptedTitle()) && empty($publication->getAdaptedContent())) {
                $publication->setStatus('failed');
                $publication->setErrorMessage('Contenu vide');
                return ['success' => false, 'error' => 'Contenu vide'];
            }

            $platform = $publication->getSocialAccount()->getPlatform();
            
            switch ($platform) {
                case 'reddit':
                    return $this->publishToReddit($publication, $destination);
                    
                case 'twitter':
                    return $this->publishToTwitter($publication, $destination);
                    
                default:
                    throw new \InvalidArgumentException("Plateforme non supportée: {$platform}");
            }
            
        } catch (\Exception $e) {
            $publication->setStatus('failed');
            $publication->setErrorMessage($e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function publishToReddit(PostPublication $publication, Destination $destination): array
    {
        try {
            $result = $this->redditApi->submitPost(
                $publication->getAdaptedTitle(),
                $publication->getAdaptedContent(),
                $destination->getName(), // subreddit
                $publication->getSocialAccount()
            );

            $publication->setStatus('published');
            $publication->setPublishedAt(new \DateTimeImmutable());
            $publication->setPlatformUrl($result['url'] ?? null);

            return ['success' => true, 'url' => $result['url'] ?? null];
            
        } catch (\Exception $e) {
            $publication->setStatus('failed');
            $publication->setErrorMessage($e->getMessage());
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function publishToTwitter(PostPublication $publication, Destination $destination): array
    {
        // TODO: Implémenter l'API Twitter
        throw new \Exception('API Twitter pas encore implémentée');
    }
}