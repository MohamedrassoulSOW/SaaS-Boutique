<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\Shop;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Shop $shop */
        $shop = $options['shop'];

        $builder
            ->add('name', TextType::class, ['label' => 'Nom'])
            ->add('reference', TextType::class, ['label' => 'Référence', 'required' => false])
            ->add('barcode', TextType::class, ['label' => 'Code-barres', 'required' => false])
            ->add('brand', TextType::class, ['label' => 'Marque', 'required' => false])
            ->add('category', EntityType::class, [
                'class' => Category::class,
                'choice_label' => 'name',
                'required' => false,
                'label' => 'Catégorie',
                'query_builder' => fn (EntityRepository $er) => $er->createQueryBuilder('c')
                    ->andWhere('c.shop = :shop')->setParameter('shop', $shop)->orderBy('c.name'),
            ])
            ->add('purchasePrice', NumberType::class, [
                'label' => 'Prix d\'achat (FCFA)',
                'scale' => 0,
                'html5' => true,
                'disabled' => !$options['show_margin'],
                'attr' => $options['show_margin'] ? [] : ['class' => 'd-none'],
                'label_attr' => $options['show_margin'] ? [] : ['class' => 'd-none'],
            ])
            ->add('salePrice', NumberType::class, [
                'label' => 'Prix de vente (FCFA)',
                'scale' => 0,
                'html5' => true,
            ])
            ->add('quantity', IntegerType::class, ['label' => 'Quantité'])
            ->add('minStock', IntegerType::class, ['label' => 'Stock minimum'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'required' => false])
            ->add('photoFile', FileType::class, [
                'label' => 'Photo (stockée en base)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new Assert\File(
                        maxSize: '2M',
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                        mimeTypesMessage: 'Image JPEG, PNG, WEBP ou GIF uniquement.',
                    ),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
            'show_margin' => true,
        ]);
        $resolver->setRequired(['shop']);
        $resolver->setAllowedTypes('show_margin', 'bool');
    }
}