<?php

namespace App\Controller;

use App\Repository\DestinationRepository;
use App\Repository\PostPublicationRepository;
use App\Repository\PostRepository;
use App\Repository\SocialAccountRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    #[IsGranted('ROLE_USER')]
    public function index(
        DestinationRepository $destinationRepository,
        PostRepository $postRepository,
        PostPublicationRepository $publicationRepository,
        SocialAccountRepository $accountRepository
    ): Response {
        $user = $this->getUser();

        // ✅ OPTIMISATION : Requêtes optimisées pour le dashboard
        $destinations = $destinationRepository->findActiveByUser($user);
        
        // ✅ NOUVEAU : Utiliser la méthode optimisée pour les posts récents
        $recentPosts = $postRepository->findRecentByUserWithPublications($user, 5);
        
        // ✅ OPTIMISATION : Stats rapides sans charger tous les posts
        $stats = $publicationRepository->getUserStats($user);
        $publishedCount = $stats['published'] ?? 0;
        $pendingCount = $stats['pending'] ?? 0;
        
        $connectedAccounts = $accountRepository->count([
            'user' => $user,
            'isActive' => true
        ]);

        return $this->render('home/index.html.twig', [
            'destinations' => $destinations,
            'recentPosts' => $recentPosts,
            'publishedCount' => $publishedCount,
            'pendingCount' => $pendingCount,
            'connectedAccounts' => $connectedAccounts,
        ]);
    }
}