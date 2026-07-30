<?php

namespace App\Form;

use App\Entity\Shop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ShopFiscalType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('merchantTaxId', TextType::class, [
                'label' => 'NINEA / Identifiant fiscal',
                'mapped' => false,
                'required' => false,
                'attr' => ['placeholder' => 'Ex. SN-DKR-XXXX'],
            ])
            ->add('taxEnabled', CheckboxType::class, [
                'label' => 'Activer la TVA sur les ventes',
                'required' => false,
            ])
            ->add('vatRate', NumberType::class, [
                'label' => 'Taux TVA (%) — vide = taux plateforme',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'max' => 100, 'step' => '0.01', 'placeholder' => 'Ex. 18'],
            ])
            ->add('pricesIncludeTax', CheckboxType::class, [
                'label' => 'Les prix catalogue sont en TTC',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Shop::class]);
    }
}
