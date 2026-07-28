<?php

namespace App\Form;

use App\Entity\Subscription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminMerchantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email (identifiant de connexion)',
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Mot de passe temporaire'],
                'second_options' => ['label' => 'Confirmer le mot de passe'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => [
                    new Assert\NotBlank(),
                    new Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.'),
                ],
            ])
            ->add('companyName', TextType::class, [
                'label' => 'Raison sociale / entreprise',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('legalForm', ChoiceType::class, [
                'label' => 'Forme juridique',
                'required' => false,
                'placeholder' => 'Choisir…',
                'choices' => [
                    'Entreprise individuelle (EI)' => 'EI',
                    'SARL' => 'SARL',
                    'SAS / SA' => 'SAS',
                    'GIE' => 'GIE',
                    'Association' => 'Association',
                    'Autre' => 'Autre',
                ],
            ])
            ->add('taxId', TextType::class, [
                'label' => 'NINEA / Identifiant fiscal',
                'required' => false,
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'RCCM',
                'required' => false,
            ])
            ->add('representativeTitle', TextType::class, [
                'label' => 'Qualité (Gérant, Directeur…)',
                'required' => false,
                'data' => 'Gérant',
            ])
            ->add('address', TextType::class, [
                'label' => 'Adresse',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'required' => false,
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'data' => 'Sénégal',
                'required' => false,
            ])
            ->add('plan', ChoiceType::class, [
                'label' => 'Formule initiale',
                'choices' => [
                    'Gratuit' => Subscription::PLAN_FREE,
                    'Basique' => Subscription::PLAN_BASIC,
                    'Pro' => Subscription::PLAN_PRO,
                ],
                'data' => Subscription::PLAN_BASIC,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
