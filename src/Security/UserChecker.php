<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Refuse l'authentification des comptes désactivés ou suspendus.
 */
class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est désactivé. Contactez le support.');
        }

        if ($user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException('Ce compte est suspendu. Contactez le support.');
        }
    }

    public function checkPostAuth(UserInterface $user, ?TokenInterface $token = null): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive() || $user->isSuspended()) {
            throw new CustomUserMessageAccountStatusException('Connexion refusée.');
        }
    }
}
