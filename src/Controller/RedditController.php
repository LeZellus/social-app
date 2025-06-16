<?php

namespace App\Controller;

use App\Form\RedditPostType;
use App\Repository\RedditRepository;
use App\Service\RedditApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/reddit', name: 'reddit_')]
class RedditController extends AbstractController
{
    public function __construct(
        private readonly RedditApiService $redditApi
    ) {}

    #[Route('/', name: 'home')]
    public function index(RedditRepository $redditRepository): Response
    {
        return $this->render('reddit/index.html.twig', [
            'is_connected' => $this->redditApi->isConnected(),
            'subreddits' => $redditRepository->findAll(),
        ]);
    }

    #[Route('/post', name: 'create_post')]
    public function createPost(Request $request, RedditRepository $redditRepository): Response
    {
        if (!$this->redditApi->isConnected()) {
            $this->addFlash('error', 'Vous devez vous connecter à Reddit d\'abord');
            return $this->redirectToRoute('reddit_home');
        }

        // Vérifier qu'il y a des subreddits enregistrés
        if ($redditRepository->count() === 0) {
            $this->addFlash('error', 'Aucun subreddit enregistré. Ajoutez-en un dans l\'admin.');
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
                        return $this->render('reddit/create_post.html.twig', ['form' => $form]);
                    }
                    
                    $result = $this->redditApi->postText(
                        $data['subreddit'],
                        $data['title'],
                        $data['text']
                    );
                } else {
                    if (empty($data['url'])) {
                        $this->addFlash('error', 'L\'URL est requise');
                        return $this->render('reddit/create_post.html.twig', ['form' => $form]);
                    }
                    
                    $result = $this->redditApi->postLink(
                        $data['subreddit'],
                        $data['title'],
                        $data['url']
                    );
                }

                $this->addFlash('success', 'Post créé avec succès sur r/' . $data['subreddit'] . ' !');
                return $this->redirectToRoute('reddit_home');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur Reddit : ' . $e->getMessage());
            }
        }

        return $this->render('reddit/create_post.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/connect', name: 'connect')]
    public function connect(): Response
    {
        $redirectUri = $this->generateUrl('reddit_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
        
        $authUrl = $this->redditApi->getAuthorizationUrl($redirectUri);
        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'callback')]
    public function callback(Request $request): Response
    {
        // Debug - vérifiez que la route est bien atteinte
        dump('Callback atteint !');
        dump($request->query->all());
        
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        
        if (!$code || !$state) {
            $this->addFlash('error', 'Code ou state manquant');
            return $this->redirectToRoute('reddit_home');
        }

        try {
            $redirectUri = $request->getSchemeAndHttpHost() . $this->generateUrl('reddit_callback');
            dump('Redirect URI utilisé: ' . $redirectUri);
            
            $this->redditApi->handleCallback($code, $state, $redirectUri);
            
            $this->addFlash('success', 'Connecté à Reddit avec succès !');
        } catch (\Exception $e) {
            dump('Erreur détaillée: ' . $e->getMessage());
            $this->addFlash('error', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('reddit_home');
    }

    #[Route('/disconnect', name: 'disconnect')]
    public function disconnect(): Response
    {
        $this->redditApi->disconnect();
        $this->addFlash('success', 'Déconnecté de Reddit');
        
        return $this->redirectToRoute('reddit_home');
    }

    #[Route('/posts/{subreddit}', name: 'posts')]
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