<?php

namespace App\Service;

use App\Entity\SocialAccount;
use App\Entity\User;
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
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $userAgent
    ) {}

    public function getAuthorizationUrl(string $redirectUri): string
    {
        $state = bin2hex(random_bytes(16));
        $this->requestStack->getSession()->set('reddit_oauth_state', $state);

        $params = http_build_query([
            'client_id' => $this->clientId,
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
        $session = $this->requestStack->getSession();
        $storedState = $session->get('reddit_oauth_state');

        if ($state !== $storedState) {
            throw new \Exception('État OAuth invalide');
        }

        // Échanger le code contre un token
        $tokenData = $this->exchangeCodeForToken($code, $redirectUri);
        
        // Récupérer les infos utilisateur
        $userInfo = $this->getUserInfo($tokenData['access_token']);
        
        // Créer ou mettre à jour le SocialAccount
        $socialAccount = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
        
        if (!$socialAccount) {
            $socialAccount = new SocialAccount();
            $socialAccount->setUser($user);
            $socialAccount->setPlatform('reddit');
            $this->entityManager->persist($socialAccount);
        }

        $socialAccount->setAccountName($userInfo['name']);
        $socialAccount->setAccessToken($tokenData['access_token']);
        $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
        $socialAccount->setIsActive(true);

        if (isset($tokenData['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
            $socialAccount->setTokenExpiresAt($expiresAt);
        }

        $this->entityManager->flush();

        // Stocker en session pour compatibilité
        $session->set('reddit_access_token', $tokenData['access_token']);
        $session->set('reddit_token_expiry', time() + ($tokenData['expires_in'] ?? 3600));
        $session->remove('reddit_oauth_state');
    }

    private function exchangeCodeForToken(string $code, string $redirectUri): array
    {
        $response = $this->httpClient->request('POST', self::TOKEN_URL, [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($this->clientId . ':' . $this->clientSecret),
                'Content-Type' => 'application/x-www-form-urlencoded',
                'User-Agent' => $this->userAgent,
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
        ]);

        return $response->toArray();
    }

    private function getUserInfo(string $accessToken): array
    {
        $response = $this->httpClient->request('GET', self::OAUTH_URL . '/api/v1/me', [
            'headers' => [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent' => $this->userAgent,
            ],
        ]);

        return $response->toArray();
    }

    public function isConnected(User $user = null): bool
    {
        if ($user) {
            $account = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
            return $account && $account->isActive() && $account->isTokenValid();
        }

        // Fallback pour compatibilité
        $session = $this->requestStack->getSession();
        $token = $session->get('reddit_access_token');
        $expiry = $session->get('reddit_token_expiry', 0);
        
        return $token && time() < $expiry;
    }

    private function getAccessToken(User $user = null): string
    {
        if ($user) {
            $account = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
            if ($account && $account->isTokenValid()) {
                return $account->getAccessToken();
            }
            throw new \Exception('Token Reddit invalide ou expiré');
        }

        // Fallback pour compatibilité
        $session = $this->requestStack->getSession();
        $token = $session->get('reddit_access_token');
        $expiry = $session->get('reddit_token_expiry', 0);

        if (!$token || time() >= $expiry) {
            throw new \Exception('Token Reddit manquant ou expiré');
        }

        return $token;
    }

    public function postText(string $subreddit, string $title, string $text, User $user = null): array
    {
        $token = $this->getAccessToken($user);

        $response = $this->httpClient->request('POST', self::OAUTH_URL . '/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => $this->userAgent,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'sr' => $subreddit,
                'kind' => 'self',
                'title' => $title,
                'text' => $text,
            ]),
        ]);

        return $response->toArray();
    }

    public function postLink(string $subreddit, string $title, string $url, User $user = null): array
    {
        $token = $this->getAccessToken($user);

        $response = $this->httpClient->request('POST', self::OAUTH_URL . '/api/submit', [
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'User-Agent' => $this->userAgent,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'sr' => $subreddit,
                'kind' => 'link',
                'title' => $title,
                'url' => $url,
            ]),
        ]);

        return $response->toArray();
    }

    public function getSubredditPosts(string $subreddit, string $sort = 'hot', int $limit = 25): array
    {
        $response = $this->httpClient->request('GET', sprintf('https://www.reddit.com/r/%s/%s.json', $subreddit, $sort), [
            'headers' => ['User-Agent' => $this->userAgent],
            'query' => ['limit' => $limit],
        ]);

        return $response->toArray();
    }

    public function disconnect(User $user = null): void
    {
        if ($user) {
            $account = $this->socialAccountRepository->findByUserAndPlatform($user, 'reddit');
            if ($account) {
                $account->setIsActive(false);
                $account->setAccessToken(null);
                $account->setRefreshToken(null);
                $this->entityManager->flush();
            }
        }

        // Nettoyer la session
        $session = $this->requestStack->getSession();
        $session->remove('reddit_access_token');
        $session->remove('reddit_token_expiry');
        $session->remove('reddit_oauth_state');
    }
}