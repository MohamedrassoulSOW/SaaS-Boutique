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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AdminContractDraftType extends AbstractType
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
                'label' => 'Entrepreneur',
                'placeholder' => 'Choisir un entrepreneur',
            ])
            ->add('shop', EntityType::class, [
                'class' => Shop::class,
                'choice_label' => static fn (Shop $s) => sprintf('%s (%s)', $s->getName(), $s->getMerchant()?->getCompanyName()),
                'label' => 'Entreprise existante (optionnel)',
                'required' => false,
                'placeholder' => 'Aucune — discuter avant création',
                'help' => 'Laissez vide pour un contrat de discussion (entreprise proposée ci-dessous).',
            ])
            ->add('proposedShopName', TextType::class, [
                'label' => 'Nom d\'entreprise proposé',
                'required' => false,
            ])
            ->add('proposedShopAddress', TextType::class, [
                'label' => 'Adresse proposée',
                'required' => false,
            ])
            ->add('proposedShopPhone', TextType::class, [
                'label' => 'Téléphone proposé',
                'required' => false,
            ])
            ->add('proposedShopEmail', EmailType::class, [
                'label' => 'Email entreprise proposé',
                'required' => false,
            ])
            ->add('plan', ChoiceType::class, [
                'label' => 'Formule',
                'choices' => [
                    'Basique' => Subscription::PLAN_BASIC,
                    'Pro' => Subscription::PLAN_PRO,
                ],
            ])
            ->add('billingPeriod', ChoiceType::class, [
                'label' => 'Périodicité',
                'choices' => [
                    'Mensuel' => ShopContract::BILLING_MONTHLY,
                    'Annuel' => ShopContract::BILLING_ANNUAL,
                ],
            ])
            ->add('price', NumberType::class, [
                'label' => 'Montant (FCFA)',
                'html5' => true,
                'scale' => 0,
                'constraints' => [new Assert\NotBlank(), new Assert\PositiveOrZero()],
            ])
            ->add('durationMonths', IntegerType::class, [
                'label' => 'Durée d\'engagement (mois)',
                'constraints' => [new Assert\NotBlank(), new Assert\Range(min: 1, max: 60)],
            ])
            ->add('discussionNotes', TextareaType::class, [
                'label' => 'Notes de discussion (internes / visibles sur le contrat si renseignées)',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('sharedWithMerchant', ChoiceType::class, [
                'label' => 'Visibilité entrepreneur',
                'choices' => [
                    'Oui' => true,
                    'Non' => false,
                ],
                'expanded' => true,
                'multiple' => false,
                'required' => true,
                'empty_data' => false,
                'choice_value' => static fn (?bool $choice): string => null === $choice ? '' : ($choice ? '1' : '0'),
                'help' => 'Œil ouvert = visible sur le dashboard entrepreneur. Œil barré = brouillon interne.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ShopContract::class]);
    }
}
