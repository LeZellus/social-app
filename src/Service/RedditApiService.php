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
        error_log("=== DEBUT CALLBACK REDDIT AVEC DEBUG ===");
        
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
        
        error_log("User info reçu: " . json_encode($userInfo));
        error_log("Token data: " . json_encode(array_keys($tokenData))); // Ne pas logger les tokens complets
        
        // Rechercher un compte existant (actif ou inactif)
        error_log("Recherche compte existant pour user_id={$user->getId()}, platform=reddit");
        
        $socialAccount = $this->socialAccountRepository->findOneBy([
            'user' => $user,
            'platform' => 'reddit'
        ]);
        
        if ($socialAccount) {
            error_log("Compte trouvé en base: ID={$socialAccount->getId()}, active={$socialAccount->isActive()}, account_name={$socialAccount->getAccountName()}");
        } else {
            error_log("Aucun compte trouvé, création d'un nouveau");
        }
        
        if (!$socialAccount) {
            // Créer un nouveau compte
            error_log("Création nouveau SocialAccount");
            $socialAccount = new SocialAccount();
            $socialAccount->setUser($user);
            $socialAccount->setPlatform('reddit');
            $socialAccount->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($socialAccount);
            error_log("Nouveau compte persisté en mémoire");
        } else {
            error_log("Mise à jour du compte existant");
        }

        // Mettre à jour les données
        error_log("Configuration des données du compte");
        $socialAccount->setAccountName($userInfo['name']);
        $socialAccount->setAccessToken($tokenData['access_token']);
        $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
        $socialAccount->setIsActive(true);

        if (isset($tokenData['expires_in'])) {
            $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
            $socialAccount->setTokenExpiresAt($expiresAt);
            error_log("Token expire le: " . $expiresAt->format('Y-m-d H:i:s'));
        }

        error_log("Tentative de flush...");
        try {
            $this->entityManager->flush();
            error_log("✅ FLUSH RÉUSSI!");
            
            // Vérifier que le compte est bien en base
            $verifyAccount = $this->socialAccountRepository->findOneBy([
                'user' => $user,
                'platform' => 'reddit'
            ]);
            
            if ($verifyAccount) {
                error_log("✅ Vérification: compte bien présent en base avec ID=" . $verifyAccount->getId());
            } else {
                error_log("❌ Vérification: compte introuvable en base après flush!");
            }
            
        } catch (\Exception $e) {
            error_log("❌ ERREUR LORS DU FLUSH: " . $e->getMessage());
            error_log("Type d'exception: " . get_class($e));
            
            // Analyser le type d'erreur
            $errorMessage = $e->getMessage();
            if (strpos($errorMessage, 'UNIQ_USER_PLATFORM') !== false || 
                strpos($errorMessage, 'Duplicate entry') !== false ||
                strpos($errorMessage, '1062') !== false) {
                
                error_log("⚠️ Détection violation contrainte unique");
                
                // Rafraîchir l'EntityManager et récupérer le compte existant
                error_log("Clear de l'EntityManager");
                $this->entityManager->clear();
                
                error_log("Re-recherche du compte existant");
                $socialAccount = $this->socialAccountRepository->findOneBy([
                    'user' => $user,
                    'platform' => 'reddit'
                ]);
                
                if ($socialAccount) {
                    error_log("Compte récupéré après clear: ID={$socialAccount->getId()}");
                    
                    // Mettre à jour le compte existant
                    $socialAccount->setAccountName($userInfo['name']);
                    $socialAccount->setAccessToken($tokenData['access_token']);
                    $socialAccount->setRefreshToken($tokenData['refresh_token'] ?? null);
                    $socialAccount->setIsActive(true);
                    
                    if (isset($tokenData['expires_in'])) {
                        $expiresAt = new \DateTimeImmutable('+' . $tokenData['expires_in'] . ' seconds');
                        $socialAccount->setTokenExpiresAt($expiresAt);
                    }
                    
                    error_log("Tentative de flush du compte existant...");
                    $this->entityManager->flush();
                    error_log("✅ Mise à jour du compte existant réussie");
                } else {
                    error_log("❌ Impossible de récupérer le compte après clear");
                    throw new \Exception("Impossible de créer ou récupérer le compte social");
                }
            } else {
                error_log("❌ Erreur non liée à la contrainte unique");
                throw $e;
            }
        }
        
        // Nettoyer la session
        $session->set('reddit_access_token', $tokenData['access_token']);
        $session->remove('reddit_oauth_state');
        
        error_log("=== FIN CALLBACK REDDIT DEBUG ===");
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