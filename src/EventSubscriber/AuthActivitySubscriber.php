<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLogger;
use App\Service\ShopContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Trace connexion / déconnexion pour audits (qui était à la caisse).
 */
class AuthActivitySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogger $activityLogger,
        private ShopContext $shopContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if (!$user instanceof User) {
            return;
        }

        $shop = $this->shopContext->getCurrentShop($user);
        $this->activityLogger->log(
            'auth.login',
            'Connexion : '.$user->getEmail(),
            $user,
            $shop
        );
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();
        if (!$user instanceof User) {
            return;
        }

        $shop = $this->shopContext->getCurrentShop($user);
        $this->activityLogger->log(
            'auth.logout',
            'Déconnexion : '.$user->getEmail(),
            $user,
            $shop
        );
    }
}
