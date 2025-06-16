<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
        ]);
    }

    #[Route('/reddit/{subreddit}', name: 'reddit_posts')]
    public function redditPosts(string $subreddit, RedditApiService $reddit): Response
    {
        $posts = $reddit->getSubredditPosts($subreddit, 'hot', 10);
        
        return $this->json($posts);
    }
}
