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
            $platform = $publication->getSocialAccount()->getPlatform();
            
            switch ($platform) {
                case 'reddit':
                    return $this->publishToReddit($publication);
                    
                case 'twitter':
                    return $this->publishToTwitter($publication);
                    
                default:
                    throw new \Exception("Plateforme non supportée: {$platform}");
            }
        } catch (\Exception $e) {
            $publication->markAsFailed($e->getMessage());
            
            return [
                'success' => false,
                'publication' => $publication,
                'error' => $e->getMessage()
            ];
        }
    }

    private function publishToReddit(PostPublication $publication): array
    {
        $subreddit = $publication->getSubreddit();
        $title = $publication->getAdaptedTitle();
        $content = $publication->getAdaptedContent();
        $account = $publication->getSocialAccount();

        if ($publication->getPost()->getMediaFiles()) {
            // Post avec lien/média
            $mediaUrl = $publication->getPost()->getMediaFiles()[0] ?? null;
            $response = $this->redditApi->submitPost($title, $mediaUrl, $subreddit, $account);
        } else {
            // Post texte
            $response = $this->redditApi->submitPost($title, $content, $subreddit, $account);
        }

        if (isset($response['json']['data']['name'])) {
            $postId = $response['json']['data']['name'];
            $postUrl = "https://reddit.com/r/{$subreddit}/comments/" . str_replace('t3_', '', $postId);
            
            $publication->markAsPublished($postId, $postUrl, $response);
            
            return [
                'success' => true,
                'publication' => $publication,
                'response' => $response
            ];
        } else {
            throw new \Exception('Erreur lors de la publication Reddit: ' . json_encode($response));
        }
    }

    private function publishToTwitter(PostPublication $publication): array
    {
        // TODO: Implémenter l'API Twitter
        throw new \Exception('Publication Twitter pas encore implémentée');
    }

    /**
     * Obtenir les statistiques de publication par destination
     */
    public function getDestinationStats(Destination $destination): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        
        $stats = $qb
            ->select('pp.status, COUNT(pp.id) as count')
            ->from(PostPublication::class, 'pp')
            ->where('pp.destination = :destination')
            ->andWhere('pp.socialAccount = :account')
            ->setParameter('destination', $destination->getName())
            ->setParameter('account', $destination->getSocialAccount())
            ->groupBy('pp.status')
            ->getQuery()
            ->getResult();

        $result = [
            'total' => 0,
            'published' => 0,
            'pending' => 0,
            'failed' => 0,
            'scheduled' => 0
        ];

        foreach ($stats as $stat) {
            $result[$stat['status']] = (int) $stat['count'];
            $result['total'] += (int) $stat['count'];
        }

        return $result;
    }
}