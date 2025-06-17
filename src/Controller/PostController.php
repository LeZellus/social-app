<?php
// src/Controller/PostController.php

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
        // ✅ OPTIMISATION : Une seule requête avec tous les joins
        $posts = $postRepository->findForIndex($this->getUser());

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
            'is_edit' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $publishOption = $form->get('publishOption')->getData();
            $selectedDestinations = $form->get('destinations')->getData();
            
            // 🔥 FIX : Définir le statut AVANT de sauvegarder
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

            // Sauvegarder le post FIRST
            $entityManager->persist($post);
            $entityManager->flush();

            // 🔥 FIX : Créer les publications seulement si des destinations sont sélectionnées
            if (!empty($selectedDestinations)) {
                $destinationIds = [];
                foreach ($selectedDestinations as $destination) {
                    $destinationIds[] = $destination->getId();
                }
                
                // Créer les publications
                $publications = $this->publicationService->createPublicationsForDestinations($post, $destinationIds);
                
                // 🔥 FIX CRITIQUE : Publier immédiatement si "now" est sélectionné
                if ($publishOption === 'now') {
                    // ✅ RECHARGEMENT : Récupérer les publications depuis la base pour éviter les problèmes de cache
                    $entityManager->refresh($post);
                    
                    $results = [];
                    $totalPublications = 0;
                    $successfulPublications = 0;
                    
                    foreach ($post->getPostPublications() as $publication) {
                        if ($publication->getStatus() === 'pending') {
                            $totalPublications++;
                            $result = $this->publicationService->publishSinglePublication($publication);
                            $results[] = $result;
                            
                            if ($result['success']) {
                                $successfulPublications++;
                            }
                        }
                    }
                    
                    // Sauvegarder les changements de statut
                    $entityManager->flush();
                    
                    // Messages utilisateur
                    if ($totalPublications === 0) {
                        $this->addFlash('warning', 'Aucune publication à effectuer.');
                    } elseif ($successfulPublications === $totalPublications) {
                        $this->addFlash('success', "Post publié avec succès sur {$successfulPublications} destination(s) !");
                    } else {
                        $this->addFlash('warning', "Post publié sur {$successfulPublications}/{$totalPublications} destination(s).");
                        
                        // Afficher les erreurs détaillées
                        foreach ($results as $index => $result) {
                            if (!$result['success']) {
                                $this->addFlash('error', "Erreur publication #{$index}: " . $result['error']);
                            }
                        }
                    }
                } else {
                    $destinationCount = count($selectedDestinations);
                    if ($publishOption === 'schedule') {
                        $this->addFlash('success', "Post programmé pour {$destinationCount} destination(s) !");
                    } else {
                        $this->addFlash('success', "Post créé en brouillon pour {$destinationCount} destination(s) !");
                    }
                }
            } else {
                // Aucune destination sélectionnée
                if ($publishOption === 'draft') {
                    $this->addFlash('success', 'Post créé en brouillon !');
                } else {
                    $this->addFlash('warning', 'Post créé mais aucune destination sélectionnée pour la publication.');
                }
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
            'is_edit' => true,
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
                    $post->setScheduledAt(null);
                    break;
            }

            $entityManager->flush();

            // Gestion des publications pour l'édition
            if (!empty($selectedDestinations)) {
                $this->updatePostPublications($post, $selectedDestinations, $entityManager);
                
                // Si publication immédiate, publier maintenant
                if ($publishOption === 'now') {
                    $entityManager->refresh($post);
                    
                    $results = [];
                    $totalPublications = 0;
                    $successfulPublications = 0;
                    
                    foreach ($post->getPostPublications() as $publication) {
                        if ($publication->getStatus() === 'pending') {
                            $totalPublications++;
                            $result = $this->publicationService->publishSinglePublication($publication);
                            $results[] = $result;
                            
                            if ($result['success']) {
                                $successfulPublications++;
                            }
                        }
                    }
                    
                    $entityManager->flush();
                    
                    if ($totalPublications === 0) {
                        $this->addFlash('info', 'Post modifié - aucune nouvelle publication à effectuer.');
                    } elseif ($successfulPublications === $totalPublications) {
                        $this->addFlash('success', "Post modifié et publié sur {$successfulPublications} destination(s) !");
                    } else {
                        $this->addFlash('warning', "Post publié sur {$successfulPublications}/{$totalPublications} destination(s).");
                    }
                } else {
                    $this->addFlash('success', 'Post modifié avec succès !');
                }
            } else {
                $this->removeUnpublishedPublications($post, $entityManager);
                $this->addFlash('success', 'Post modifié et publications mises à jour !');
            }

            return $this->redirectToRoute('app_posts');
        }

        return $this->render('posts/edit.html.twig', [
            'form' => $form,
            'post' => $post,
        ]);
    }

    private function updatePostPublications(Post $post, $selectedDestinations, EntityManagerInterface $entityManager): void
    {
        // Supprimer les publications non publiées
        $publicationsToRemove = $post->getPostPublications()->filter(function($pub) {
            return in_array($pub->getStatus(), ['pending', 'failed', 'scheduled']);
        });

        foreach ($publicationsToRemove as $publication) {
            $entityManager->remove($publication);
            $post->removePostPublication($publication);
        }

        // Créer les nouvelles publications
        if (!empty($selectedDestinations)) {
            $destinationIds = [];
            foreach ($selectedDestinations as $destination) {
                $destinationIds[] = $destination->getId();
            }
            
            $this->publicationService->createPublicationsForDestinations($post, $destinationIds);
        }

        $entityManager->flush();
    }

    private function removeUnpublishedPublications(Post $post, EntityManagerInterface $entityManager): void
    {
        $publicationsToRemove = $post->getPostPublications()->filter(function($pub) {
            return $pub->getStatus() !== 'published';
        });

        foreach ($publicationsToRemove as $publication) {
            $entityManager->remove($publication);
            $post->removePostPublication($publication);
        }

        $entityManager->flush();
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