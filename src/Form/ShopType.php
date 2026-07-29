<?php

namespace App\Form;

use App\Entity\Shop;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ShopType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom de la boutique'])
            ->add('address', TextType::class, ['label' => 'Adresse', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => false])
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
            ->add('merchantTaxId', TextType::class, [
                'label' => 'NINEA / Identifiant fiscal (entreprise)',
                'mapped' => false,
                'required' => false,
            ])
            ->add('taxEnabled', CheckboxType::class, [
                'label' => 'Activer la TVA sur les ventes',
                'required' => false,
            ])
            ->add('vatRate', NumberType::class, [
                'label' => 'Taux TVA boutique (%) — vide = taux plateforme',
                'required' => false,
                'scale' => 2,
                'html5' => true,
                'attr' => ['min' => 0, 'max' => 100, 'step' => '0.01', 'placeholder' => 'Ex. 18'],
            ])
            ->add('pricesIncludeTax', CheckboxType::class, [
                'label' => 'Les prix produits sont en TTC',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Shop::class]);
    }
}
