<?php

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

        $builder
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500'],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500',
                    'rows' => 6
                ],
            ])
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
                'label' => 'Destinations',
                'help' => 'Sélectionnez les destinations où publier ce contenu',
                'attr' => ['class' => 'destinations-checkboxes'],
            ])
            ->add('publishOption', ChoiceType::class, [
                'label' => 'Publication',
                'choices' => [
                    'Publier maintenant' => 'now',
                    'Programmer la publication' => 'schedule',
                    'Sauvegarder en brouillon' => 'draft',
                ],
                'mapped' => false,
                'data' => 'now',
                'attr' => ['class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500'],
            ])
            ->add('scheduledAt', DateTimeType::class, [
                'label' => 'Date de publication',
                'required' => false,
                'widget' => 'single_text',
                'attr' => [
                    'class' => 'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500',
                    'style' => 'display: none;'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
            'user' => null,
        ]);

        $resolver->setRequired('user');
    }
}