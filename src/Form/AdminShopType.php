<?php

namespace App\Form;

use App\Entity\Merchant;
use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\Subscription;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminShopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('merchant', EntityType::class, [
                'class' => Merchant::class,
                'choice_label' => static fn (Merchant $m) => sprintf(
                    '%s — %s (%s)',
                    $m->getCompanyName(),
                    $m->getUser()?->getFullName(),
                    $m->getUser()?->getEmail()
                ),
                'label' => 'Commerçant demandeur',
                'placeholder' => 'Choisir un commerçant',
                'attr' => [
                    'onchange' => 'if (this.value) { window.location = "?merchant=" + this.value; }',
                ],
            ])
            // Infos personnelles commerçant
            ->add('personFirstName', TextType::class, [
                'label' => 'Prénom du commerçant',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('personLastName', TextType::class, [
                'label' => 'Nom du commerçant',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('personPhone', TextType::class, [
                'label' => 'Téléphone personnel',
                'mapped' => false,
                'required' => false,
            ])
            ->add('personEmail', EmailType::class, [
                'label' => 'Email personnel',
                'mapped' => false,
                'disabled' => true,
                'required' => false,
            ])
            // Infos entreprise
            ->add('companyName', TextType::class, [
                'label' => 'Raison sociale / entreprise',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('legalForm', ChoiceType::class, [
                'label' => 'Forme juridique',
                'mapped' => false,
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
                'mapped' => false,
                'required' => false,
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'RCCM / Registre de commerce',
                'mapped' => false,
                'required' => false,
            ])
            ->add('representativeTitle', TextType::class, [
                'label' => 'Qualité du représentant (Gérant, Directeur…)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('companyAddress', TextType::class, [
                'label' => 'Adresse de l\'entreprise',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('postalCode', TextType::class, [
                'label' => 'Code postal',
                'mapped' => false,
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Ville',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('country', TextType::class, [
                'label' => 'Pays',
                'mapped' => false,
                'data' => 'Sénégal',
                'constraints' => [new Assert\NotBlank()],
            ])
            // Boutique
            ->add('name', TextType::class, ['label' => 'Nom de la boutique'])
            ->add('address', TextType::class, [
                'label' => 'Adresse de la boutique',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('phone', TextType::class, ['label' => 'Téléphone boutique', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email boutique', 'required' => false])
            ->add('logoFile', FileType::class, [
                'label' => 'Logo (stocké en base)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                    ),
                ],
            ])
            // Abonnement / contrat
            ->add('plan', ChoiceType::class, [
                'label' => 'Formule d\'abonnement',
                'mapped' => false,
                'choices' => [
                    'Basique' => Subscription::PLAN_BASIC,
                    'Pro' => Subscription::PLAN_PRO,
                ],
                'data' => Subscription::PLAN_BASIC,
            ])
            ->add('billingPeriod', ChoiceType::class, [
                'label' => 'Périodicité de l\'abonnement',
                'mapped' => false,
                'choices' => [
                    'Mensuel' => ShopContract::BILLING_MONTHLY,
                    'Annuel' => ShopContract::BILLING_ANNUAL,
                ],
                'data' => ShopContract::BILLING_MONTHLY,
                'help' => 'Retard max : 15 jours (mensuel) ou 1 mois / 30 jours (annuel) → rupture immédiate.',
            ])
            ->add('price', NumberType::class, [
                'label' => 'Montant de l\'abonnement (FCFA)',
                'mapped' => false,
                'html5' => true,
                'scale' => 0,
                'data' => 15000,
                'help' => 'Montant par mois (mensuel) ou pour l\'année (annuel).',
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('durationMonths', IntegerType::class, [
                'label' => 'Durée d\'engagement (mois)',
                'mapped' => false,
                'data' => 12,
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 60)],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $shop = $event->getData();
            $form = $event->getForm();
            if (!$shop instanceof Shop || !$shop->getMerchant()) {
                return;
            }
            self::fillMerchantFields($form, $shop->getMerchant());
        });
    }

    private static function fillMerchantFields($form, Merchant $merchant): void
    {
        $user = $merchant->getUser();
        $form->get('personFirstName')->setData($user?->getFirstName());
        $form->get('personLastName')->setData($user?->getLastName());
        $form->get('personPhone')->setData($user?->getPhone());
        $form->get('personEmail')->setData($user?->getEmail());
        $form->get('companyName')->setData($merchant->getCompanyName());
        $form->get('legalForm')->setData($merchant->getLegalForm());
        $form->get('taxId')->setData($merchant->getTaxId());
        $form->get('registrationNumber')->setData($merchant->getRegistrationNumber());
        $form->get('representativeTitle')->setData($merchant->getRepresentativeTitle() ?: 'Gérant');
        $form->get('companyAddress')->setData($merchant->getAddress());
        $form->get('postalCode')->setData($merchant->getPostalCode());
        $form->get('city')->setData($merchant->getCity());
        $form->get('country')->setData($merchant->getCountry() ?: 'Sénégal');
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Shop::class]);
    }
}
