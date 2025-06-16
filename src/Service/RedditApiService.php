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
            throw new \Exception('Aucune clef Reddit configurée');
        }

        $session = $this->requestStack->getSession();
        $storedState = $session->get('reddit_oauth_state');
        if ($state !== $storedState) {
            throw new \Exception('État OAuth invalide');
        }

        $tokenData = $this->exchangeCodeForToken($code, $redirectUri, $credentials);
        $userInfo = $this->getUserInfo($tokenData['access_token'], $credentials);
        
        error_log("User: " . $userInfo['name'] . ", Token reçu");

        // IMPORTANT: Vérifier d'abord si un compte existe
        $socialAccount = $this->socialAccountRepository->findOneBy([
            'user' => $user,
            'platform' => 'reddit'
        ]);

        if ($socialAccount) {
            error_log("Mise à jour du compte existant ID: " . $socialAccount->getId());
            
            // Mettre à jour le compte existant
            $socialAccount->setAccountName($userInfo['name']);
            $socialAccount->setAccessToken($tokenData['access_token']);
            $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
            $socialAccount->setIsActive(true);
            
            if (isset($tokenData['expires_in'])) {
                $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                $socialAccount->setTokenExpiresAt($expiresAt);
            }
        } else {
            error_log("Création d'un nouveau compte Reddit");
            
            // Créer un nouveau compte
            $socialAccount = new SocialAccount();
            $socialAccount->setUser($user);
            $socialAccount->setPlatform('reddit');
            $socialAccount->setAccountName($userInfo['name']);
            $socialAccount->setAccessToken($tokenData['access_token']);
            $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
            $socialAccount->setIsActive(true);
            
            if (isset($tokenData['expires_in'])) {
                $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                $socialAccount->setTokenExpiresAt($expiresAt);
            }
            
            $this->entityManager->persist($socialAccount);
        }

        // Sauvegarder
        try {
            $this->entityManager->flush();
            error_log("✅ Compte sauvegardé - ID: " . $socialAccount->getId());
        } catch (\Exception $e) {
            error_log("❌ Erreur flush: " . $e->getMessage());
            throw $e;
        }
        
        // Nettoyer la session
        $session->set('reddit_access_token', $tokenData['access_token']);
        $session->remove('reddit_oauth_state');
        
        error_log("=== FIN CALLBACK REDDIT ===");
    }

    public function hasValidCredentials(User $user): bool
    {
        $credentials = $this->getUserCredentials($user, 'reddit');
        return $credentials !== null && 
            !empty($credentials->getClientId()) && 
            !empty($credentials->getClientSecret());
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

    private function safeSaveSocialAccount(User $user, array $userInfo, array $tokenData): SocialAccount
    {
        // Commencer une transaction
        $this->entityManager->beginTransaction();
        
        try {
            // Vérifier si un compte existe déjà
            $socialAccount = $this->socialAccountRepository->findOneBy([
                'user' => $user,
                'platform' => 'reddit'
            ]);

            if (!$socialAccount) {
                $socialAccount = new SocialAccount();
                $socialAccount->setUser($user);
                $socialAccount->setPlatform('reddit');
                $this->entityManager->persist($socialAccount);
            }

            // Mettre à jour les données
            $socialAccount->setAccountName($userInfo['name']);
            $socialAccount->setAccessToken($tokenData['access_token']);
            $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
            $socialAccount->setIsActive(true);
            
            if (isset($tokenData['expires_in'])) {
                $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                $socialAccount->setTokenExpiresAt($expiresAt);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();
            
            return $socialAccount;
            
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            // Si contrainte unique, récupérer le compte existant
            if (strpos($e->getMessage(), 'UNIQ') !== false || 
                strpos($e->getMessage(), 'Duplicate') !== false) {
                
                $existingAccount = $this->socialAccountRepository->findOneBy([
                    'user' => $user,
                    'platform' => 'reddit'
                ]);
                
                if ($existingAccount) {
                    // Mise à jour directe sans transaction
                    $existingAccount->setAccountName($userInfo['name']);
                    $existingAccount->setAccessToken($tokenData['access_token']);
                    $existingAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
                    $existingAccount->setIsActive(true);
                    
                    if (isset($tokenData['expires_in'])) {
                        $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                        $existingAccount->setTokenExpiresAt($expiresAt);
                    }
                    
                    $this->entityManager->flush();
                    return $existingAccount;
                }
            }
            
            throw $e;
        }
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