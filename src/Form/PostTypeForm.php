<?php
// src/Form/PostType.php

namespace App\Form;

use App\Entity\Post;
use App\Entity\Destination;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];
        $isEdit = $options['is_edit'] ?? false;
        $post = $options['data'] ?? null;

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white'
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white',
                    'rows' => 6
                ],
            ]);

        // Champs de publication disponibles en création ET en édition
        $builder
            ->add('destinations', EntityType::class, [
                'class' => Destination::class,
                'choice_label' => function (Destination $destination) {
                    return $destination->getDisplayName() . ' (' . $destination->getSocialAccount()->getPlatform() . ')';
                },
                'query_builder' => function ($repository) use ($user) {
                    return $repository->createQueryBuilder('d')
                        ->join('d.socialAccount', 'sa')
                        ->where('d.user = :user')
                        ->andWhere('d.isActive = true')
                        ->andWhere('sa.isActive = true')
                        ->orderBy('sa.platform', 'ASC')
                        ->addOrderBy('d.displayName', 'ASC')
                        ->setParameter('user', $user);
                },
                'multiple' => true,
                'expanded' => true,
                'mapped' => false,
                'label' => $isEdit ? 'Modifier les destinations' : 'Destinations',
                'help' => $isEdit ? 'Modifiez les destinations où publier ce contenu' : 'Sélectionnez les destinations où publier ce contenu',
                'attr' => ['class' => 'destinations-checkboxes'],
                'data' => $isEdit && $post ? $this->getPostDestinations($post) : null,
            ])
            ->add('publishOption', ChoiceType::class, [
                'label' => 'Publication',
                'choices' => [
                    'Publier maintenant' => 'now',
                    'Programmer la publication' => 'schedule',
                    'Sauvegarder en brouillon' => 'draft',
                ],
                'mapped' => false,
                'data' => $isEdit ? $this->getPublishOptionFromPost($post) : 'now',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white'],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white',
                ],
            ]);
    }

    private function getPostDestinations(Post $post): array
    {
        $destinations = [];
        foreach ($post->getPostPublications() as $publication) {
            $socialAccount = $publication->getSocialAccount();
            $destinationName = $publication->getDestination();
            
            foreach ($socialAccount->getDestinations() as $destination) {
                if ($destination->getName() === $destinationName) {
                    $destinations[] = $destination;
                    break;
                }
            }
        }
        return $destinations;
    }

    private function getPublishOptionFromPost(?Post $post): string
    {
        if (!$post) {
            return 'draft';
        }

        switch ($post->getStatus()) {
            case 'published':
                return 'now';
            case 'scheduled':
                return 'schedule';
            case 'draft':
            default:
                return 'draft';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'user' => null,
            'is_edit' => false,
        ]);

        $resolver->setRequired('user');
    }
}