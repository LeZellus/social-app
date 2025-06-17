<?php

namespace App\Controller;

use App\Entity\Destination;
use App\Form\DestinationType;
use App\Repository\DestinationRepository;
use App\Repository\SocialAccountRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Service\RedditApiService;

#[Route('/destinations')]
#[IsGranted('ROLE_USER')]
class DestinationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RedditApiService $redditApi  // ← Injection du service
    ) {}


    #[Route('/', name: 'app_destinations')]
    public function index(DestinationRepository $destinationRepository): Response
    {
        $destinations = $destinationRepository->findBy(['user' => $this->getUser()]);

        return $this->render('destinations/index.html.twig', [
            'destinations' => $destinations,
        ]);
    }

    #[Route('/new', name: 'app_destination_new')]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        SocialAccountRepository $socialAccountRepository
    ): Response {
        $destination = new Destination();
        $destination->setUser($this->getUser());

        $form = $this->createForm(DestinationType::class, $destination, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($destination->getSocialAccount()->getPlatform() === 'reddit') {
                $subreddit = str_replace('r/', '', $destination->getName());
                $rules = $this->redditApi->getSubredditRules($subreddit, $destination->getSocialAccount());
                
                # Stocker règles structurées
                $destination->setSettings([
                    'rules' => $rules,
                    'restrictions' => [
                        'min_karma' => $rules['karma_required'] ?? 0,
                        'min_account_age' => $rules['account_age_days'] ?? 0,
                        'forbidden_words' => $rules['banned_keywords'] ?? [],
                        'required_flair' => $rules['flair_required'] ?? false,
                        'title_format' => $rules['title_pattern'] ?? null,
                        'submission_type' => $rules['submission_type'] ?? 'any'  # text, link, any
                    ]
                ]);
            }
            $entityManager->persist($destination);
            $entityManager->flush();

            $this->addFlash('success', 'Destination ajoutée avec succès !');
            return $this->redirectToRoute('app_destinations');
        }

        return $this->render('destinations/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_destination_edit')]
    public function edit(
        Destination $destination,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $destination);

        $form = $this->createForm(DestinationType::class, $destination, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Destination modifiée avec succès !');
            return $this->redirectToRoute('app_destinations');
        }

        return $this->render('destinations/edit.html.twig', [
            'form' => $form,
            'destination' => $destination,
        ]);
    }

    #[Route('/{id}/toggle', name: 'app_destination_toggle')]
    public function toggle(
        Destination $destination,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('edit', $destination);

        $destination->setIsActive(!$destination->isActive());
        $entityManager->flush();

        $status = $destination->isActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', "Destination {$status} avec succès !");

        return $this->redirectToRoute('app_destinations');
    }

    #[Route('/{id}/delete', name: 'app_destination_delete', methods: ['POST'])]
    public function delete(
        Destination $destination,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('delete', $destination);

        $entityManager->remove($destination);
        $entityManager->flush();

        $this->addFlash('success', 'Destination supprimée avec succès !');
        return $this->redirectToRoute('app_destinations');
    }
}