<?php

namespace App\Form;

use App\Entity\Destination;
use App\Entity\SocialAccount;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DestinationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $user = $options['user'];

        $builder
            ->add('socialAccount', EntityType::class, [
                'class' => SocialAccount::class,
                'choice_label' => function (SocialAccount $account) {
                    return ucfirst($account->getPlatform()) . ' - ' . $account->getAccountName();
                },
                'query_builder' => function ($repository) use ($user) {
                    return $repository->createQueryBuilder('sa')
                        ->where('sa.user = :user')
                        ->andWhere('sa.isActive = true')
                        ->setParameter('user', $user);
                },
                'placeholder' => 'Choisir un compte',
                'label' => 'Compte social',
            ])
            ->add('name', TextType::class, [
                'label' => 'Nom de la destination',
                'help' => 'Ex: r/gamedev pour Reddit, ou votre propre nom',
            ])
            ->add('displayName', TextType::class, [
                'label' => 'Nom d\'affichage',
                'required' => false,
                'help' => 'Optionnel: nom plus lisible',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Destination active',
                'required' => false,
                'data' => true,
            ]);

        // Ajouter des champs spécifiques selon la plateforme
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event) {
            $destination = $event->getData();
            $form = $event->getForm();

            if ($destination && $destination->getSocialAccount()) {
                $this->addPlatformSpecificFields($form, $destination->getSocialAccount()->getPlatform());
            }
        });

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            $form = $event->getForm();

            if (isset($data['socialAccount'])) {
                // Récupérer la plateforme depuis l'ID du compte
                // Pour simplifier, on peut ajouter les champs pour toutes les plateformes
                $this->addPlatformSpecificFields($form, 'reddit');
            }
        });
    }

    private function addPlatformSpecificFields($form, string $platform): void
    {
        if ($platform === 'reddit') {
            $form->add('flair', ChoiceType::class, [
                'label' => 'Flair par défaut',
                'required' => false,
                'choices' => [
                    'DevLog' => 'DevLog',
                    'Tutorial' => 'Tutorial',
                    'Showcase' => 'Showcase',
                    'Discussion' => 'Discussion',
                ],
                'mapped' => false,
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Destination::class,
            'user' => null,
        ]);

        $resolver->setRequired('user');
    }
}