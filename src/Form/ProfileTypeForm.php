<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre prénom'
                ],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre nom'
                ],
            ])
            ->add('pseudo', TextType::class, [
                'label' => 'Pseudo',
                'required' => false,
                'help' => 'Nom affiché publiquement (optionnel)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Votre pseudo'
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => [
                    'class' => 'form-control',
                    'readonly' => true
                ],
            ])
            ->add('website', TextType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://monsite.com'
                ],
            ])
            ->add('bio', TextareaType::class, [
                'label' => 'Biographie',
                'required' => false,
                'help' => 'Quelques mots sur vous (maximum 500 caractères)',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'maxlength' => 500,
                    'placeholder' => 'Présentez-vous en quelques mots...'
                ],
            ])
            ->add('timezone', ChoiceType::class, [
                'label' => 'Fuseau horaire',
                'required' => false,
                'choices' => $this->getTimezoneChoices(),
                'placeholder' => 'Sélectionnez votre fuseau horaire',
                'attr' => [
                    'class' => 'form-control'
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Mettre à jour le profil',
                'attr' => [
                    'class' => 'btn btn-primary'
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }

    private function getTimezoneChoices(): array
    {
        $timezones = [
            'Europe/Paris' => 'Europe/Paris (UTC+1)',
            'Europe/London' => 'Europe/London (UTC+0)',
            'America/New_York' => 'America/New_York (UTC-5)',
            'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
            'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
            'Australia/Sydney' => 'Australia/Sydney (UTC+10)',
            'UTC' => 'UTC',
        ];

        // Optionnel : récupérer tous les fuseaux horaires dynamiquement
        /*
        $timezones = [];
        foreach (\DateTimeZone::listIdentifiers() as $timezone) {
            $timezones[$timezone] = $timezone;
        }
        */

        return $timezones;
    }
}