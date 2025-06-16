<?php

namespace App\Controller;

use App\Entity\Post;
use App\Form\PostType;
use App\Service\PublicationService;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/posts')]
#[IsGranted('ROLE_USER')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PublicationService $publicationService
    ) {}

    #[Route('/', name: 'app_posts')]
    public function index(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('posts/index.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/new', name: 'app_post_new')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $post = new Post();
        $post->setUser($this->getUser());

        $form = $this->createForm(PostType::class, $post, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publishOption = $form->get('publishOption')->getData();
            $selectedDestinations = $form->get('destinations')->getData();
            
            // Définir le statut selon l'option choisie
            switch ($publishOption) {
                case 'now':
                    $post->setStatus('published');
                    break;
                case 'schedule':
                    $post->setStatus('scheduled');
                    $scheduledAt = $form->get('scheduledAt')->getData();
                    if ($scheduledAt) {
                        $post->setScheduledAt($scheduledAt);
                    }
                    break;
                case 'draft':
                default:
                    $post->setStatus('draft');
                    break;
            }

            $entityManager->persist($post);
            $entityManager->flush();

            // Créer les publications pour les destinations sélectionnées
            if (!empty($selectedDestinations)) {
                $destinationIds = [];
                foreach ($selectedDestinations as $destination) {
                    $destinationIds[] = $destination->getId();
                }
                
                $publications = $this->publicationService->createPublicationsForDestinations(
                    $post,
                    $destinationIds
                );

                // Si publication immédiate, publier maintenant
                if ($publishOption === 'now') {
                    $results = [];
                    foreach ($publications as $publication) {
                        $result = $this->publicationService->publishSinglePublication($publication);
                        $results[] = $result;
                    }
                    
                    $successCount = count(array_filter($results, fn($r) => $r['success']));
                    $totalCount = count($results);
                    
                    if ($successCount === $totalCount) {
                        $this->addFlash('success', "Post publié avec succès sur {$successCount} destination(s) !");
                    } else {
                        $this->addFlash('warning', "Post publié sur {$successCount}/{$totalCount} destination(s). Vérifiez les erreurs.");
                    }
                } else {
                    $this->addFlash('success', 'Post créé avec succès !');
                }
            } else {
                $this->addFlash('success', 'Post créé en brouillon !');
            }

            return $this->redirectToRoute('app_posts');
        }

        return $this->render('posts/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_post_edit')]
    public function edit(Post $post, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $post);

        $form = $this->createForm(PostType::class, $post, [
            'user' => $this->getUser(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            
            $this->addFlash('success', 'Post modifié avec succès !');
            return $this->redirectToRoute('app_posts');
        }

        return $this->render('posts/edit.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    #[Route('/{id}/publish', name: 'app_post_publish')]
    public function publish(Post $post, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('edit', $post);

        if ($post->getStatus() !== 'draft') {
            $this->addFlash('error', 'Seuls les brouillons peuvent être publiés de cette façon.');
            return $this->redirectToRoute('app_posts');
        }

        try {
            // Récupérer les publications existantes pour ce post ou créer des nouvelles
            if ($post->getPostPublications()->isEmpty()) {
                // Pas de publications existantes, créer pour toutes les destinations actives
                $publications = $this->publicationService->createPublicationsForAllDestinations($post);
            } else {
                // Utiliser les publications existantes
                $publications = $post->getPostPublications()->toArray();
            }
            
            $results = [];
            foreach ($publications as $publication) {
                $result = $this->publicationService->publishSinglePublication($publication);
                $results[] = $result;
            }
            
            $successCount = count(array_filter($results, fn($r) => $r['success']));
            $totalCount = count($results);
            
            if ($successCount > 0) {
                $post->setStatus('published');
                $entityManager->flush();
                
                if ($successCount === $totalCount) {
                    $this->addFlash('success', "Post publié avec succès sur {$successCount} destination(s) !");
                } else {
                    $this->addFlash('warning', "Post publié sur {$successCount}/{$totalCount} destination(s).");
                }
            } else {
                $this->addFlash('error', 'Échec de la publication sur toutes les destinations.');
            }
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la publication : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_posts');
    }

    #[Route('/{id}/delete', name: 'app_post_delete', methods: ['POST'])]
    public function delete(Post $post, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('delete', $post);

        $entityManager->remove($post);
        $entityManager->flush();

        $this->addFlash('success', 'Post supprimé avec succès !');
        return $this->redirectToRoute('app_posts');
    }
}