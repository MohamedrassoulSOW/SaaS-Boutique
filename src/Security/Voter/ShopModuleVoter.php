<?php

namespace App\Security\Voter;

use App\Entity\User;
use App\Security\ShopPermission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, mixed>
 */
class ShopModuleVoter extends Voter
{
    public function __construct(private ShopPermission $permission)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'MODULE_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $module = strtolower(substr($attribute, \strlen('MODULE_')));

        return $this->permission->can($user, $module);
    }
}
