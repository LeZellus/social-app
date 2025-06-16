<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class RedditPostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('subreddit', TextType::class, [
                'label' => 'Subreddit',
                'attr' => ['placeholder' => 'Ex: test, programming...'],
                'constraints' => [new NotBlank(message: 'Le subreddit est requis')],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Titre de votre post'],
                'constraints' => [new NotBlank(message: 'Le titre est requis')],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type de post',
                'choices' => [
                    'Texte' => 'text',
                    'Lien' => 'link',
                ],
                'expanded' => true,
                'data' => 'text',
            ])
            ->add('text', TextareaType::class, [
                'label' => 'Contenu',
                'required' => false,
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Écrivez votre contenu ici...'
                ],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'required' => false,
                'attr' => ['placeholder' => 'https://example.com'],
                'constraints' => [new Url(message: 'URL invalide')],
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Poster sur Reddit',
                'attr' => ['class' => 'btn btn-primary'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}