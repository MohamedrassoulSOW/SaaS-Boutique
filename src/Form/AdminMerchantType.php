<?php

namespace App\Form;

use App\Entity\Merchant;
use App\Entity\Subscription;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use App\Validator\PasswordPolicy;
use Symfony\Component\Validator\Constraints as Assert;

class AdminMerchantType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

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
                'required' => false,
                'first_options' => [
                    'label' => $isEdit
                        ? 'Nouveau mot de passe (optionnel)'
                        : 'Mot de passe initial (optionnel — sinon invitation email)',
                    'help' => $isEdit ? null : 'Jamais envoyé par email. Un lien sécurisé est toujours envoyé.',
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => PasswordPolicy::optionalConstraints(),
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
                ...($isEdit ? [] : ['data' => 'Gérant']),
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
                'required' => false,
                ...($isEdit ? [] : ['data' => 'Sénégal']),
            ])
            ->add('plan', ChoiceType::class, [
                'label' => 'Formule',
                'choices' => [
                    'Gratuit' => Subscription::PLAN_FREE,
                    'Basique' => Subscription::PLAN_BASIC,
                    'Pro' => Subscription::PLAN_PRO,
                ],
                ...($isEdit ? [] : ['data' => Subscription::PLAN_BASIC]),
            ]);

        if ($isEdit) {
            $builder->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ]);
        }

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event) use ($options): void {
            /** @var Merchant|null $merchant */
            $merchant = $options['merchant'];
            if (!$merchant instanceof Merchant || !$merchant->getUser()) {
                return;
            }

            $form = $event->getForm();
            $user = $merchant->getUser();
            $form->get('firstName')->setData($user->getFirstName());
            $form->get('lastName')->setData($user->getLastName());
            $form->get('email')->setData($user->getEmail());
            $form->get('phone')->setData($user->getPhone());
            $form->get('companyName')->setData($merchant->getCompanyName());
            $form->get('legalForm')->setData($merchant->getLegalForm());
            $form->get('taxId')->setData($merchant->getTaxId());
            $form->get('registrationNumber')->setData($merchant->getRegistrationNumber());
            $form->get('representativeTitle')->setData($merchant->getRepresentativeTitle());
            $form->get('address')->setData($merchant->getAddress());
            $form->get('city')->setData($merchant->getCity());
            $form->get('country')->setData($merchant->getCountry());
            $form->get('plan')->setData($merchant->getSubscription()?->getPlan() ?? Subscription::PLAN_BASIC);
            if ($form->has('isActive')) {
                $form->get('isActive')->setData($user->isActive());
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'is_edit' => false,
            'merchant' => null,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
        $resolver->setAllowedTypes('merchant', ['null', Merchant::class]);
    }
}
