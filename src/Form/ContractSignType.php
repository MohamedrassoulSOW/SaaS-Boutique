<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ContractSignType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['sign_platform']) {
            $builder->add('platformSignedBy', TextType::class, [
                'label' => 'Signataire plateforme',
                'constraints' => [new Assert\NotBlank()],
                'data' => $options['default_platform_signer'],
            ]);
        }

        if ($options['sign_merchant']) {
            $builder
                ->add('merchantSignedBy', TextType::class, [
                    'label' => 'Nom du signataire entrepreneur',
                    'constraints' => [new Assert\NotBlank()],
                    'data' => $options['default_merchant_signer'],
                ])
                ->add('merchantSignedTitle', TextType::class, [
                    'label' => 'Qualité',
                    'constraints' => [new Assert\NotBlank()],
                    'data' => $options['default_merchant_title'],
                ]);
        }

        $builder->add('confirm', CheckboxType::class, [
            'label' => 'Je confirme avoir lu le contrat et signer électroniquement ce document',
            'mapped' => false,
            'constraints' => [
                new Assert\IsTrue(message: 'Vous devez confirmer la lecture et la signature.'),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'sign_platform' => false,
            'sign_merchant' => false,
            'default_platform_signer' => '',
            'default_merchant_signer' => '',
            'default_merchant_title' => 'Gérant',
        ]);
        $resolver->setAllowedTypes('sign_platform', 'bool');
        $resolver->setAllowedTypes('sign_merchant', 'bool');
    }
}
