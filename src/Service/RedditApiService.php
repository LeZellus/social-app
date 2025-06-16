<?php

namespace App\Service;

use App\Entity\ApiCredentials;
use App\Entity\SocialAccount;
use App\Entity\User;
use App\Repository\ApiCredentialsRepository;
use App\Repository\SocialAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RedditApiService
{
    private const OAUTH_URL = 'https://oauth.reddit.com';
    private const AUTH_URL = 'https://www.reddit.com/api/v1/authorize';
    private const TOKEN_URL = 'https://www.reddit.com/api/v1/access_token';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $entityManager,
        private readonly SocialAccountRepository $socialAccountRepository,
        private readonly ApiCredentialsRepository $apiCredentialsRepository
    ) {}

    public function getAuthorizationUrl(string $redirectUri, User $user): string
    {
        $credentials = $this->getUserCredentials($user, 'reddit');
        if (!$credentials) {
            throw new \Exception('Aucune clef Reddit configurée. Veuillez d\'abord configurer vos clefs API.');
        }

        $state = bin2hex(random_bytes(16));
        $this->requestStack->getSession()->set('reddit_oauth_state', $state);

        $params = http_build_query([
            'client_id' => $credentials->getClientId(),
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'duration' => 'permanent',
            'scope' => 'identity submit read'
        ]);

        return self::AUTH_URL . '?' . $params;
    }

    public function handleCallback(string $code, string $state, string $redirectUri, User $user): void
    {
        try {
            error_log("=== DEBUT CALLBACK REDDIT ===");
            
            error_log("ÉTAPE 1: Récupération credentials");
            $credentials = $this->getUserCredentials($user, 'reddit');
            if (!$credentials) {
                error_log("ERREUR: Aucune clef Reddit configurée");
                throw new \Exception('Aucune clef Reddit configurée');
            }
            error_log("✓ Clefs récupérées");

            error_log("ÉTAPE 2: Validation state");
            $session = $this->requestStack->getSession();
            $storedState = $session->get('reddit_oauth_state');
            if ($state !== $storedState) {
                error_log("ERREUR: État OAuth invalide");
                throw new \Exception('État OAuth invalide');
            }
            error_log("✓ État OAuth validé");

            error_log("ÉTAPE 3: Échange code/token");
            $tokenData = $this->exchangeCodeForToken($code, $redirectUri, $credentials);
            error_log("✓ Token récupéré");
            
            error_log("ÉTAPE 4: Récupération infos utilisateur");
            $userInfo = $this->getUserInfo($tokenData['access_token'], $credentials);
            error_log("✓ Infos utilisateur récupérées: " . $userInfo['name']);
            
            error_log("ÉTAPE 5: Recherche SocialAccount existant");
            $socialAccount = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
            
            if (!$socialAccount) {
                error_log("ÉTAPE 6: Création nouveau SocialAccount");
                $socialAccount = new SocialAccount();
                $socialAccount->setUser($user);
                $socialAccount->setPlatform('reddit');
                $socialAccount->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($socialAccount);
                error_log("✓ Nouveau SocialAccount créé et persisté");
            } else {
                error_log("ÉTAPE 6: Mise à jour SocialAccount existant ID: " . $socialAccount->getId());
            }

            error_log("ÉTAPE 7: Configuration données");
            $socialAccount->setAccountName($userInfo['name']);
            $socialAccount->setAccessToken($tokenData['access_token']);
            $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
            $socialAccount->setIsActive(true);

            if (isset($tokenData['expires_in'])) {
                $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                $socialAccount->setTokenExpiresAt($expiresAt);
            }
            error_log("✓ Données configurées");

            error_log("ÉTAPE 8: FLUSH");
            $this->entityManager->flush();
            error_log("✓ FLUSH RÉUSSI");
            
            error_log("ÉTAPE 9: Session");
            $session->set('reddit_access_token', $tokenData['access_token']);
            $session->remove('reddit_oauth_state');
            error_log("✓ Session mise à jour");
            
            error_log("=== FIN CALLBACK REDDIT SUCCÈS ===");
            
        } catch (\Exception $e) {
            error_log("❌ EXCEPTION DANS CALLBACK: " . $e->getMessage());
            error_log("❌ STACK TRACE: " . $e->getTraceAsString());
            throw $e;
        }
    }

    private function exchangeCodeForToken(string $code, string $redirectUri, ApiCredentials $credentials): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($credentials->getClientId() . ':' . $credentials->getClientSecret()),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $credentials->getUserAgent() ?: 'SocialApp/1.0',
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
        ]);

        return $response->toArray();
    }

    private function getUserInfo(string $accessToken, ApiCredentials $credentials): array
    {
        $response = $this->httpClient->request('GET', self::OAUTH_URL . '/api/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => $credentials->getUserAgent() ?: 'SocialApp/1.0',
            ],
        ]);

        return $response->toArray();
    }

    public function submitPost(string $title, string $text, string $subreddit, SocialAccount $account): array
    {
        $credentials = $this->getUserCredentials($account->getUser(), 'reddit');
        if (!$credentials) {
            throw new \Exception('Aucune clef Reddit configurée');
        }

        $response = $this->httpClient->request('POST', self::OAUTH_URL . '/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $account->getAccessToken(),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $credentials->getUserAgent() ?: 'SocialApp/1.0',
            ],
            'body' => http_build_query([
                'api_type' => 'json',
                'kind' => 'self',
                'title' => $title,
                'text' => $text,
                'sr' => $subreddit,
            ]),
        ]);

        return $response->toArray();
    }

    public function hasValidCredentials(User $user): bool
    {
        $credentials = $this->getUserCredentials($user, 'reddit');
        return $credentials && $credentials->isActive() && 
               $credentials->getClientId() && $credentials->getClientSecret();
    }

    private function getUserCredentials(User $user, string $platform): ?ApiCredentials
    {
        return $this->apiCredentialsRepository->findActiveByUserAndPlatform($user, $platform);
    }

    public function disconnect(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove('reddit_access_token');
        $session->remove('reddit_token_expiry');
        $session->remove('reddit_oauth_state');
    }
}