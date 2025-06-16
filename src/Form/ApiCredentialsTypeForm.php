<?php

namespace App\Form;

use App\Entity\ApiCredentials;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ApiCredentialsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $platform = $options['platform'];

        $builder
            ->add('clientId', TextType::class, [
                'label' => $this->getClientIdLabel($platform),
                'help' => $this->getClientIdHelp($platform),
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => $this->getClientIdPlaceholder($platform)
                ]
            ])
            ->add('clientSecret', PasswordType::class, [
                'label' => $this->getClientSecretLabel($platform),
                'help' => $this->getClientSecretHelp($platform),
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '••••••••••••••••'
                ]
            ]);

        // Champs spécifiques à Reddit
        if ($platform === 'reddit') {
            $builder->add('userAgent', TextType::class, [
                'label' => 'User Agent',
                'help' => 'Ex: MonApp/1.0.0 (by u/votre_nom)',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'MonApp/1.0.0 (by u/votre_nom)'
                ]
            ]);
        }

        $builder->add('isActive', CheckboxType::class, [
            'label' => 'Activer ces clefs',
            'required' => false,
            'data' => true,
        ]);
    }

    private function getClientIdLabel(string $platform): string
    {
        return match ($platform) {
            'reddit' => 'Client ID Reddit',
            'twitter' => 'API Key Twitter',
            default => 'Client ID'
        };
    }

    private function getClientIdHelp(string $platform): string
    {
        return match ($platform) {
            'reddit' => 'Votre Client ID obtenu sur https://www.reddit.com/prefs/apps',
            'twitter' => 'Votre API Key obtenue sur https://developer.twitter.com',
            default => 'Votre identifiant client API'
        };
    }

    private function getClientIdPlaceholder(string $platform): string
    {
        return match ($platform) {
            'reddit' => 'abc123def456...',
            'twitter' => 'xxxxxxxxxxxxxxxxxxxx',
            default => 'Votre Client ID'
        };
    }

    private function getClientSecretLabel(string $platform): string
    {
        return match ($platform) {
            'reddit' => 'Client Secret Reddit',
            'twitter' => 'API Secret Key Twitter',
            default => 'Client Secret'
        };
    }

    private function getClientSecretHelp(string $platform): string
    {
        return match ($platform) {
            'reddit' => 'Votre Client Secret Reddit (gardez-le secret !)',
            'twitter' => 'Votre API Secret Key Twitter (gardez-la secrète !)',
            default => 'Votre clef secrète API'
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApiCredentials::class,
            'platform' => null,
        ]);

        $resolver->setRequired('platform');
    }
}