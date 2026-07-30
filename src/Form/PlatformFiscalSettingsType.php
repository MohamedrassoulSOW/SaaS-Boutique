<?php

namespace App\Form;

use App\Entity\PlatformFiscalSettings;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PlatformFiscalSettingsType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('legalName', TextType::class, [
                'label' => 'Raison sociale',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('legalForm', TextType::class, [
                'label' => 'Forme juridique',
                'required' => false,
            ])
            ->add('taxId', TextType::class, [
                'label' => 'NINEA / Identifiant fiscal',
                'required' => false,
            ])
            ->add('registrationNumber', TextType::class, [
                'label' => 'RCCM',
                'required' => false,
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
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email fiscal / contact',
                'required' => false,
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'required' => false,
            ])
            ->add('defaultVatRate', NumberType::class, [
                'label' => 'Taux TVA par défaut (%)',
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'max' => 100, 'step' => '0.01'],
                'constraints' => [
                    new Assert\NotNull(),
                    new Assert\Range(min: 0, max: 100),
                ],
            ])
            ->add('defaultPricesIncludeTax', CheckboxType::class, [
                'label' => 'Prix catalogue TTC par défaut (entreprises)',
                'required' => false,
            ])
            ->add('taxOnSubscriptions', CheckboxType::class, [
                'label' => 'Appliquer la TVA sur les abonnements plateforme',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlatformFiscalSettings::class]);
    }
}
