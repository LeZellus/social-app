<?php

namespace App\Controller;

use App\Service\RedditApiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/reddit')]
#[IsGranted('ROLE_USER')]
class RedditController extends AbstractController
{
    #[Route('/connect', name: 'reddit_connect')]
    public function connect(RedditApiService $redditApi): Response
    {
        $user = $this->getUser();
        
        // Vérifier que l'utilisateur a configuré ses clefs
        if (!$redditApi->hasValidCredentials($user)) {
            $this->addFlash('error', 'Vous devez d\'abord configurer vos clefs API Reddit.');
            return $this->redirectToRoute('app_api_credentials_new', ['platform' => 'reddit']);
        }

        $redirectUri = $this->generateUrl('reddit_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        $authUrl = $redditApi->getAuthorizationUrl($redirectUri, $user);

        return $this->redirect($authUrl);
    }

    #[Route('/callback', name: 'reddit_callback')]
    public function callback(Request $request, RedditApiService $redditApi): Response
    {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        if ($error) {
            $this->addFlash('error', 'Erreur lors de la connexion Reddit : ' . $error);
            return $this->redirectToRoute('app_social_accounts');
        }

        if (!$code || !$state) {
            $this->addFlash('error', 'Paramètres manquants pour la connexion Reddit');
            return $this->redirectToRoute('app_social_accounts');
        }

        try {
            $redirectUri = $this->generateUrl('reddit_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
            $redditApi->handleCallback($code, $state, $redirectUri, $this->getUser());
            
            $this->addFlash('success', 'Compte Reddit connecté avec succès !');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_social_accounts');
    }
}