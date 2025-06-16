<?php

namespace App\Controller;

use App\Repository\PostPublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/destinations', name: 'destination_')]
#[IsGranted('ROLE_USER')]
class DestinationController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(PostPublicationRepository $postPublicationRepo): Response
    {
        // Récupérer toutes les destinations uniques utilisées par l'utilisateur
        $existingDestinations = $postPublicationRepo->createQueryBuilder('pp')
            ->select('DISTINCT pp.destination')
            ->join('pp.socialAccount', 'sa')
            ->where('sa.user = :user')
            ->andWhere('sa.platform = :platform')
            ->setParameter('user', $this->getUser())
            ->setParameter('platform', 'reddit')
            ->getQuery()
            ->getSingleColumnResult();

        return $this->render('destination/index.html.twig', [
            'destinations' => $existingDestinations,
        ]);
    }

    #[Route('/add', name: 'add', methods: ['POST'])]
    public function add(Request $request): Response
    {
        $subreddit = $request->request->get('subreddit');
        
        if (empty($subreddit)) {
            $this->addFlash('error', 'Le nom du subreddit est requis');
            return $this->redirectToRoute('destination_index');
        }

        // Nettoyer le nom du subreddit
        $cleanSubreddit = 'r/' . ltrim($subreddit, 'r/');

        $this->addFlash('success', "Destination '{$cleanSubreddit}' ajoutée à votre liste !");
        
        return $this->redirectToRoute('destination_index');
    }
}