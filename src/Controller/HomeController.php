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

        // Récupérer les statistiques
        $destinations = $destinationRepository->findBy(['user' => $user, 'isActive' => true]);
        $recentPosts = $postRepository->findBy(['user' => $user], ['createdAt' => 'DESC'], 5);
        
        $publishedCount = $publicationRepository->count([
            'post' => $postRepository->findBy(['user' => $user]),
            'status' => 'published'
        ]);
        
        $pendingCount = $publicationRepository->count([
            'post' => $postRepository->findBy(['user' => $user]),
            'status' => 'pending'
        ]);
        
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