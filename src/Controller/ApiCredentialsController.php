<?php

namespace App\Controller;

use App\Entity\ApiCredentials;
use App\Form\ApiCredentialsType;
use App\Repository\ApiCredentialsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profile/api-credentials')]
#[IsGranted('ROLE_USER')]
class ApiCredentialsController extends AbstractController
{
    #[Route('/', name: 'app_api_credentials_index')]
    public function index(ApiCredentialsRepository $repository): Response
    {
        $credentials = $repository->findBy(['user' => $this->getUser()]);

        return $this->render('api_credentials/index.html.twig', [
            'credentials' => $credentials,
        ]);
    }

    #[Route('/new/{platform}', name: 'app_api_credentials_new')]
    public function new(string $platform, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifier que la plateforme est supportée
        $supportedPlatforms = ['reddit', 'twitter'];
        if (!in_array($platform, $supportedPlatforms)) {
            throw $this->createNotFoundException('Plateforme non supportée');
        }

        $credentials = new ApiCredentials();
        $credentials->setUser($this->getUser());
        $credentials->setPlatform($platform);

        $form = $this->createForm(ApiCredentialsType::class, $credentials, [
            'platform' => $platform
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($credentials);
            $entityManager->flush();

            $this->addFlash('success', "Clefs API {$platform} configurées avec succès !");
            return $this->redirectToRoute('app_api_credentials_index');
        }

        return $this->render('api_credentials/new.html.twig', [
            'form' => $form,
            'platform' => $platform,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_api_credentials_edit')]
    public function edit(ApiCredentials $credentials, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $credentials);

        $form = $this->createForm(ApiCredentialsType::class, $credentials, [
            'platform' => $credentials->getPlatform()
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $credentials->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Clefs API mises à jour avec succès !');
            return $this->redirectToRoute('app_api_credentials_index');
        }

        return $this->render('api_credentials/edit.html.twig', [
            'form' => $form,
            'credentials' => $credentials,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_api_credentials_delete', methods: ['POST'])]
    public function delete(ApiCredentials $credentials, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $credentials);

        $entityManager->remove($credentials);
        $entityManager->flush();

        $this->addFlash('success', 'Clefs API supprimées avec succès !');
        return $this->redirectToRoute('app_api_credentials_index');
    }

    #[Route('/{id}/toggle', name: 'app_api_credentials_toggle')]
    public function toggle(ApiCredentials $credentials, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $credentials);

        $credentials->setIsActive(!$credentials->isActive());
        $credentials->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $status = $credentials->isActive() ? 'activées' : 'désactivées';
        $this->addFlash('success', "Clefs API {$status} avec succès !");

        return $this->redirectToRoute('app_api_credentials_index');
    }
}