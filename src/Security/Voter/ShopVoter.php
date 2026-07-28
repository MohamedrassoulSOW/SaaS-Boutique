<?php

namespace App\Security\Voter;

use App\Entity\Shop;
use App\Entity\User;
use App\Service\ShopContext;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Shop>
 */
class ShopVoter extends Voter
{
    public const VIEW = 'SHOP_VIEW';
    public const EDIT = 'SHOP_EDIT';
    public const MANAGE = 'SHOP_MANAGE';

    public function __construct(private ShopContext $shopContext)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE], true)
            && $subject instanceof Shop;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || $user->isAdmin()) {
            // Admin plateforme : pas d'accès opérationnel boutique
            return false;
        }

        /** @var Shop $shop */
        $shop = $subject;

        return match ($attribute) {
            self::VIEW => $this->shopContext->userCanAccess($user, $shop),
            self::EDIT, self::MANAGE => $user->isMerchant()
                && $this->shopContext->userCanAccess($user, $shop),
            default => false,
        };
    }
}
