<?php

namespace App\Controller;

use App\Entity\SocialAccount;
use App\Repository\SocialAccountRepository;
use App\Service\RedditApiService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/accounts')]
#[IsGranted('ROLE_USER')]
class SocialAccountController extends AbstractController
{
    #[Route('/', name: 'app_social_accounts')]
    public function index(SocialAccountRepository $repository): Response
    {
        $accounts = $repository->findBy(['user' => $this->getUser()]);

        return $this->render('social_accounts/index.html.twig', [
            'accounts' => $accounts,
        ]);
    }

    #[Route('/connect/{platform}', name: 'app_social_account_connect')]
    public function connect(string $platform): Response
    {
        switch ($platform) {
            case 'reddit':
                return $this->redirectToRoute('reddit_connect');
            
            case 'twitter':
                $this->addFlash('warning', 'Twitter n\'est pas encore implémenté');
                return $this->redirectToRoute('app_social_accounts');
            
            default:
                $this->addFlash('error', 'Plateforme non supportée');
                return $this->redirectToRoute('app_social_accounts');
        }
    }

    #[Route('/{id}/disconnect', name: 'app_social_account_disconnect')]
    public function disconnect(
        SocialAccount $account,
        EntityManagerInterface $entityManager,
        RedditApiService $redditApi
    ): Response {
        $this->denyAccessUnlessGranted('delete', $account);

        $platform = $account->getPlatform();
        
        // Déconnexion spécifique à la plateforme
        if ($platform === 'reddit') {
            $redditApi->disconnect();
        }

        // Désactiver le compte
        $account->setIsActive(false);
        $account->setAccessToken(null);
        $account->setRefreshToken(null);
        $entityManager->flush();

        $this->addFlash('success', "Compte {$platform} déconnecté avec succès !");
        
        return $this->redirectToRoute('app_social_accounts');
    }

    #[Route('/{id}/toggle', name: 'app_social_account_toggle')]
    public function toggle(
        SocialAccount $account,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $account);

        $account->setIsActive(!$account->isActive());
        $entityManager->flush();

        $status = $account->isActive() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Compte {$status} avec succès !");

        return $this->redirectToRoute('app_social_accounts');
    }
}