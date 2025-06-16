<?php

namespace App\Controller;

use App\Form\RedditPostType;
use App\Service\RedditApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reddit')]
class RedditController extends AbstractController
{
    public function __construct(
        private readonly RedditApiService $redditApi
    ) {}

    #[Route('/', name: 'reddit_home')]
    public function index(): Response
    {
        return $this->render('reddit/index.html.twig', [
            'is_connected' => $this->redditApi->isConnected(),
        ]);
    }

    #[Route('/connect', name: 'reddit_connect')]
    public function connect(): Response
    {
        $redirectUri = $this->generateUrl('reddit_callback', [], true);
        $authUrl = $this->redditApi->getAuthorizationUrl($redirectUri);
        
        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'reddit_callback')]
    public function callback(Request $request): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        
        if (!$code || !$state) {
            $this->addFlash('error', 'Erreur lors de la connexion Reddit');
            return $this->redirectToRoute('reddit_home');
        }

        try {
            $redirectUri = $this->generateUrl('reddit_callback', [], true);
            $this->redditApi->handleCallback($code, $state, $redirectUri);
            
            $this->addFlash('success', 'Connecté à Reddit avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('reddit_home');
    }

    #[Route('/disconnect', name: 'reddit_disconnect')]
    public function disconnect(): Response
    {
        $this->redditApi->disconnect();
        $this->addFlash('success', 'Déconnecté de Reddit');
        
        return $this->redirectToRoute('reddit_home');
    }

    #[Route('/post', name: 'reddit_create_post')]
    public function createPost(Request $request): Response
    {
        if (!$this->redditApi->isConnected()) {
            $this->addFlash('error', 'Vous devez vous connecter à Reddit d\'abord');
            return $this->redirectToRoute('reddit_home');
        }

        $form = $this->createForm(RedditPostType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            try {
                if ($data['type'] === 'text') {
                    if (empty($data['text'])) {
                        $this->addFlash('error', 'Le contenu texte est requis');
                        return $this->render('reddit/create_post.html.twig', [
                            'form' => $form,
                        ]);
                    }
                    
                    $result = $this->redditApi->postText(
                        $data['subreddit'],
                        $data['title'],
                        $data['text']
                    );
                } else {
                    if (empty($data['url'])) {
                        $this->addFlash('error', 'L\'URL est requise');
                        return $this->render('reddit/create_post.html.twig', [
                            'form' => $form,
                        ]);
                    }
                    
                    $result = $this->redditApi->postLink(
                        $data['subreddit'],
                        $data['title'],
                        $data['url']
                    );
                }

                $this->addFlash('success', 'Post créé avec succès sur Reddit !');
                return $this->redirectToRoute('reddit_home');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            }
        }

        return $this->render('reddit/create_post.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/posts/{subreddit}', name: 'reddit_posts')]
    public function posts(string $subreddit): Response
    {
        try {
            $posts = $this->redditApi->getSubredditPosts($subreddit);
            
            return $this->render('reddit/posts.html.twig', [
                'subreddit' => $subreddit,
                'posts' => $posts['data']['children'] ?? [],
            ]);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
            return $this->redirectToRoute('reddit_home');
        }
    }
}