<?php

namespace App\Controller;

use App\Repository\ApiCredentialsRepository;
use App\Repository\SocialAccountRepository;
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
    public function connect(
        RedditApiService $redditApi,
        ApiCredentialsRepository $apiRepo
    ): Response {
        $user = $this->getUser();
        
        // Debug des clefs API
        $credentials = $apiRepo->findActiveByUserAndPlatform($user, 'reddit');

        if (!$credentials) {
            $this->addFlash('error', 'Aucune clef Reddit trouvée en base !');
            // Vérifier toutes les clefs de l'utilisateur
            $allCreds = $apiRepo->findBy(['user' => $user]);
            $this->addFlash('info', sprintf('Vous avez %d clefs en total', count($allCreds)));
            return $this->redirectToRoute('app_social_accounts');
        }

        $this->addFlash('info', sprintf(
            'Clefs trouvées - ID: %s, Platform: %s, Active: %s, ClientID: %s...',
            $credentials->getId(),
            $credentials->getPlatform(),
            $credentials->isActive() ? 'OUI' : 'NON',
            substr($credentials->getClientId(), 0, 8)
        ));
        
        // Vérifier que l'utilisateur a configuré ses clefs
        if (!$redditApi->hasValidCredentials($user)) {
            $this->addFlash('error', 'Vous devez d\'abord configurer vos clefs API Reddit.');
            return $this->redirectToRoute('app_api_credentials_new', ['platform' => 'reddit']);
        }

        $redirectUri = $this->generateUrl('reddit_callback', [], \Symfony\Component\Routing\Generator\UrlGeneratorInterface::ABSOLUTE_URL);
        
        $this->addFlash('info', 'Redirect URI: ' . $redirectUri);
        
        try {
            $authUrl = $redditApi->getAuthorizationUrl($redirectUri, $user);
            $this->addFlash('info', 'URL d\'auth générée, redirection vers Reddit...');
            return $this->redirect($authUrl);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur génération URL: ' . $e->getMessage());
            return $this->redirectToRoute('app_social_accounts');
        }
    }

    #[Route('/callback', name: 'reddit_callback')]
    public function callback(
        Request $request, 
        RedditApiService $redditApi,
        SocialAccountRepository $socialAccountRepository
    ): Response {
        $code = $request->query->get('code');
        $state = $request->query->get('state');
        $error = $request->query->get('error');

        $this->addFlash('info', sprintf(
            'Callback reçu - Code: %s, State: %s, Error: %s',
            $code ? 'OUI' : 'NON',
            $state ? substr($state, 0, 8) . '...' : 'NON',
            $error ?: 'NON'
        ));

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
            
            $this->addFlash('info', 'Tentative de traitement du callback...');
            
            $redditApi->handleCallback($code, $state, $redirectUri, $this->getUser());
            
            // Vérifier que le compte a bien été créé
            $redditAccount = $socialAccountRepository->findByUserAndPlatform($this->getUser(), 'reddit');
            if ($redditAccount) {
                $this->addFlash('success', sprintf(
                    'Compte Reddit @%s connecté avec succès !', 
                    $redditAccount->getAccountName()
                ));
            } else {
                $this->addFlash('error', 'La connexion a échoué - aucun compte créé en base.');
            }
            
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la connexion : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_social_accounts');
    }
}