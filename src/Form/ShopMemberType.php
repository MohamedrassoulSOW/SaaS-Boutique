<?php

namespace App\Form;

use App\Entity\ShopMember;
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
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ShopMemberType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = (bool) $options['is_edit'];

        $builder
            ->add('firstName', TextType::class, [
                'label' => 'Prénom',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nom',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email (identifiant de connexion)',
                'mapped' => false,
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('phone', TextType::class, [
                'label' => 'Téléphone',
                'mapped' => false,
                'required' => false,
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle dans la boutique',
                'choices' => [
                    'Vendeur / Caissier' => ShopMember::ROLE_CASHIER,
                    'Responsable' => ShopMember::ROLE_MANAGER,
                    'Magasinier (stock)' => ShopMember::ROLE_STOCK,
                ],
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'first_options' => [
                    'label' => $isEdit ? 'Nouveau mot de passe (optionnel)' : 'Mot de passe',
                ],
                'second_options' => [
                    'label' => 'Confirmer le mot de passe',
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'constraints' => $isEdit
                    ? [
                        new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                            if (!\is_string($value) || $value === '') {
                                return;
                            }
                            if (\strlen($value) < 8) {
                                $context->buildViolation('Au moins 8 caractères.')->addViolation();
                            }
                        }),
                    ]
                    : [
                        new Assert\NotBlank(message: 'Mot de passe obligatoire.'),
                        new Assert\Length(min: 8, minMessage: 'Au moins {{ limit }} caractères.'),
                    ],
            ]);

        $builder->addEventListener(FormEvents::PRE_SET_DATA, static function (FormEvent $event): void {
            $member = $event->getData();
            $form = $event->getForm();
            if (!$member instanceof ShopMember || !$member->getUser()) {
                return;
            }

            $user = $member->getUser();
            $form->get('firstName')->setData($user->getFirstName());
            $form->get('lastName')->setData($user->getLastName());
            $form->get('email')->setData($user->getEmail());
            $form->get('phone')->setData($user->getPhone());
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ShopMember::class,
            'is_edit' => false,
        ]);
        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
