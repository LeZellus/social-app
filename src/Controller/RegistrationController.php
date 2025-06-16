<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationForm::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword(
                $userPasswordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Account created successfully!');
            
            // Turbo-Frame response pour recharger le formulaire avec le message
            if ($request->headers->get('Turbo-Frame')) {
                return $this->render('registration/_form.html.twig', [
                    'registrationForm' => $this->createForm(RegistrationForm::class, new User()),
                ]);
            }
            
            return $this->redirectToRoute('app_login');
        }

        // En cas d'erreur, Turbo recharge automatiquement le frame
        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}