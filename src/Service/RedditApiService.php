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

        // UTILISER LA MÉTHODE SÉCURISÉE
        $socialAccount = $this->safeSaveSocialAccount($user, $userInfo, $tokenData);
        
        error_log("✅ Compte sauvegardé - ID: " . $socialAccount->getId());
        
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
        error_log("🚀 RedditApiService::submitPost - Début");
        error_log("📍 Subreddit: {$subreddit}");
        error_log("📝 Titre: {$title}");
        error_log("📄 Contenu: " . substr($text, 0, 100) . "...");
        
        $credentials = $this->getUserCredentials($account->getUser(), 'reddit');
        if (!$credentials) {
            throw new \Exception('Aucune clef Reddit configurée');
        }

        // ✅ VÉRIFICATION TOKEN
        if (!$account->getAccessToken()) {
            throw new \Exception('Aucun token d\'accès Reddit disponible');
        }

        error_log("🔑 Token disponible: " . substr($account->getAccessToken(), 0, 20) . "...");

        try {
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
                    'sr' => $subreddit, // IMPORTANT : juste le nom, pas "r/subreddit"
                    'resubmit' => 'true'
                ]),
            ]);

            $responseData = $response->toArray();
            
            error_log("📡 Réponse HTTP Status: " . $response->getStatusCode());
            error_log("📡 Réponse Reddit: " . json_encode($responseData));

            // ✅ VÉRIFICATION ERREURS REDDIT
            if (isset($responseData['json']['errors']) && !empty($responseData['json']['errors'])) {
                $errors = $responseData['json']['errors'];
                error_log("❌ Erreurs Reddit détectées: " . json_encode($errors));
                
                // Garder la réponse complète pour debug
                return $responseData;
            }

            // ✅ SUCCÈS
            if (isset($responseData['json']['data'])) {
                error_log("✅ Soumission Reddit réussie");
                return $responseData;
            }

            // ✅ RÉPONSE INATTENDUE
            error_log("⚠️ Réponse Reddit inattendue (pas d'erreur mais pas de data)");
            return $responseData;

        } catch (\Exception $e) {
            error_log("❌ Exception HTTP Reddit: " . $e->getMessage());
            
            // Si c'est une erreur HTTP, essayer de récupérer le contenu
            if (method_exists($e, 'getResponse')) {
                try {
                    $errorResponse = $e->getResponse();
                    $errorContent = $errorResponse->getContent(false);
                    error_log("❌ Contenu erreur Reddit: " . $errorContent);
                } catch (\Exception $innerE) {
                    error_log("❌ Impossible de lire le contenu d'erreur: " . $innerE->getMessage());
                }
            }
            
            throw $e;
        }
    }

    private function safeSaveSocialAccount(User $user, array $userInfo, array $tokenData): SocialAccount
    {
        // UTILISER findByUserAndPlatformIgnoreStatus pour trouver même les comptes inactifs
        $socialAccount = $this->socialAccountRepository->findByUserAndPlatformIgnoreStatus($user, 'reddit');

        if (!$socialAccount) {
            error_log("Création d'un nouveau compte Reddit");
            $socialAccount = new SocialAccount();
            $socialAccount->setUser($user);
            $socialAccount->setPlatform('reddit');
            $this->entityManager->persist($socialAccount);
        } else {
            error_log("Mise à jour du compte existant ID: " . $socialAccount->getId());
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

        try {
            $this->entityManager->flush();
            error_log("✅ Compte sauvegardé - ID: " . $socialAccount->getId());
            return $socialAccount;
        } catch (\Exception $e) {
            error_log("❌ Erreur flush: " . $e->getMessage());
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

    public function getSubredditRules(string $subreddit, SocialAccount $account): array
    {
        $credentials = $this->getUserCredentials($account->getUser(), 'reddit');
        if (!$credentials) {
            throw new \Exception('Aucune clef Reddit configurée');
        }

        try {
            $response = $this->httpClient->request('GET', 
                self::OAUTH_URL . "/r/{$subreddit}/about/rules", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $account->getAccessToken(),
                    'User-Agent' => $credentials->getUserAgent() ?: 'SocialApp/1.0',
                ]
            ]);

            $data = $response->toArray();
            
            # ✅ EXTRAIRE SEULEMENT CE QU'ON UTILISE
            $rules = [];
            if (isset($data['rules']) && is_array($data['rules'])) {
                foreach ($data['rules'] as $rule) {
                    $rules[] = [
                        'short_name' => $rule['short_name'] ?? '',
                        'description' => $rule['description'] ?? '',
                        'kind' => $rule['kind'] ?? 'all'  // comment, submission, all
                    ];
                }
            }
            
            return [
                'subreddit' => $subreddit,
                'rules_count' => count($rules),
                'rules_list' => $rules,
                'fetched_at' => date('Y-m-d H:i:s')
            ];
            
        } catch (\Exception $e) {
            # ✅ LOG POUR DEBUG
            error_log("Erreur récupération règles r/{$subreddit}: " . $e->getMessage());
            throw new \Exception("Impossible de récupérer les règles de r/{$subreddit}: " . $e->getMessage());
        }
    }
}