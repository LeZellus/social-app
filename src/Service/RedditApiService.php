<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RedditApiService
{
    private const BASE_URL = 'https://www.reddit.com/api/v1';
    private const OAUTH_URL = 'https://oauth.reddit.com';

    public function __construct(
        private HttpClientInterface $httpClient,
        private RequestStack $requestStack,
        private string $clientId,
        private string $clientSecret,
        private string $userAgent = 'Symfony-App/1.0'
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
            'duration' => 'temporary',
            'scope' => 'submit read',
        ]);

        return self::BASE_URL . '/authorize?' . $params;
    }

    public function handleCallback(string $code, string $state, string $redirectUri): string
    {
        $storedState = $this->requestStack->getSession()->get('reddit_oauth_state');
        
        if ($state !== $storedState) {
            throw new \Exception('État OAuth invalide');
        }

        $response = $this->httpClient->request('POST', self::BASE_URL . '/access_token', [
            'auth_basic' => [$this->clientId, $this->clientSecret],
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => http_build_query([
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]),
        ]);

        $data = $response->toArray();
        
        $this->requestStack->getSession()->set('reddit_access_token', $data['access_token']);
        $this->requestStack->getSession()->set('reddit_token_expiry', time() + $data['expires_in']);
        
        return $data['access_token'];
    }

    public function isConnected(): bool
    {
        $token = $this->requestStack->getSession()->get('reddit_access_token');
        $expiry = $this->requestStack->getSession()->get('reddit_token_expiry');
        
        return $token && $expiry && time() < $expiry;
    }

    private function getAccessToken(): string
    {
        if (!$this->isConnected()) {
            throw new \Exception('Non connecté à Reddit');
        }

        return $this->requestStack->getSession()->get('reddit_access_token');
    }

    public function postText(string $subreddit, string $title, string $text): array
    {
        $token = $this->getAccessToken();

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

    public function postLink(string $subreddit, string $title, string $url): array
    {
        $token = $this->getAccessToken();

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

    public function disconnect(): void
    {
        $this->requestStack->getSession()->remove('reddit_access_token');
        $this->requestStack->getSession()->remove('reddit_token_expiry');
        $this->requestStack->getSession()->remove('reddit_oauth_state');
    }
}