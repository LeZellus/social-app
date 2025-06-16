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
        error_log("=== DEBUT CALLBACK REDDIT ===");
        
        $credentials = $this->getUserCredentials($user, 'reddit');
        if (!$credentials) {
            error_log("ERREUR: Aucune clef Reddit configurée");
            throw new \Exception('Aucune clef Reddit configurée');
        }
        error_log("✓ Clefs récupérées: " . $credentials->getClientId());

        $session = $this->requestStack->getSession();
        $storedState = $session->get('reddit_oauth_state');

        if ($state !== $storedState) {
            error_log("ERREUR: État OAuth invalide - reçu: $state, attendu: $storedState");
            throw new \Exception('État OAuth invalide');
        }
        error_log("✓ État OAuth validé");

        // Échanger le code contre un token
        try {
            $tokenData = $this->exchangeCodeForToken($code, $redirectUri, $credentials);
            error_log("✓ Token récupéré: " . json_encode(array_keys($tokenData)));
        } catch (\Exception $e) {
            error_log("ERREUR échange token: " . $e->getMessage());
            throw $e;
        }
        
        // Récupérer les infos utilisateur
        try {
            $userInfo = $this->getUserInfo($tokenData['access_token'], $credentials);
            error_log("✓ Infos utilisateur: " . json_encode($userInfo));
        } catch (\Exception $e) {
            error_log("ERREUR infos utilisateur: " . $e->getMessage());
            throw $e;
        }
        
        // Créer ou mettre à jour le SocialAccount
        $socialAccount = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
        
        if (!$socialAccount) {
            error_log("Création d'un nouveau SocialAccount");
            $socialAccount = new SocialAccount();
            $socialAccount->setUser($user);
            $socialAccount->setPlatform('reddit');
            $this->entityManager->persist($socialAccount);
        } else {
            error_log("Mise à jour du SocialAccount existant");
        }

        $socialAccount->setAccountName($userInfo['name']);
        $socialAccount->setAccessToken($tokenData['access_token']);
        $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
        $socialAccount->setIsActive(true);

        if (isset($tokenData['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
            $socialAccount->setTokenExpiresAt($expiresAt);
        }

        try {
            $this->entityManager->flush();
            error_log("✓ SocialAccount sauvegardé avec succès - ID: " . $socialAccount->getId());
        } catch (\Exception $e) {
            error_log("ERREUR sauvegarde: " . $e->getMessage());
            throw $e;
        }

        $session->set('reddit_access_token', $tokenData['access_token']);
        $session->set('reddit_token_expiry', time() + ($tokenData['expires_in'] ?? 3600));
        $session->remove('reddit_oauth_state');
        
        error_log("=== FIN CALLBACK REDDIT ===");
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