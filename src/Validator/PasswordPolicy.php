<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Politique de mot de passe commune (longueur + complexité minimale).
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 10;

    /**
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    public static function constraints(bool $required = true): array
    {
        $constraints = [];
        if ($required) {
            $constraints[] = new Assert\NotBlank(message: 'Mot de passe obligatoire.');
        }

        $constraints[] = new Assert\Length(
            min: self::MIN_LENGTH,
            minMessage: 'Au moins {{ limit }} caractères.',
        );
        $constraints[] = new Assert\Regex(
            pattern: '/[A-Za-z]/',
            message: 'Ajoutez au moins une lettre.',
        );
        $constraints[] = new Assert\Regex(
            pattern: '/\d/',
            message: 'Ajoutez au moins un chiffre.',
        );

        return $constraints;
    }

    /**
     * Contrainte optionnelle (profil / édition) : vide autorisé, sinon politique.
     *
     * @return list<\Symfony\Component\Validator\Constraint>
     */
    public static function optionalConstraints(): array
    {
        return [
            new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                if (!\is_string($value) || $value === '') {
                    return;
                }
                if (\strlen($value) < PasswordPolicy::MIN_LENGTH) {
                    $context->buildViolation('Au moins {{ limit }} caractères.')
                        ->setParameter('{{ limit }}', (string) PasswordPolicy::MIN_LENGTH)
                        ->addViolation();
                }
                if (!preg_match('/[A-Za-z]/', $value)) {
                    $context->buildViolation('Ajoutez au moins une lettre.')->addViolation();
                }
                if (!preg_match('/\d/', $value)) {
                    $context->buildViolation('Ajoutez au moins un chiffre.')->addViolation();
                }
            }),
        ];
    }
}
