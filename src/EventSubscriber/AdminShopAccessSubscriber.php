<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Empêche l'admin d'accéder aux modules métier des boutiques.
 */
class AdminShopAccessSubscriber implements EventSubscriberInterface
{
    private const BLOCKED_PREFIXES = [
        '/dashboard',
        '/shops',
        '/products',
        '/categories',
        '/suppliers',
        '/customers',
        '/sales',
        '/stock',
        '/purchases',
        '/inventories',
        '/reports',
        '/vendeurs',
        '/contrats',
        '/shop/',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 5]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->tokenStorage->getToken()?->getUser();
        if (!$user instanceof User || !$user->isAdmin()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/login') || str_starts_with($path, '/logout')
            || str_starts_with($path, '/profile') || str_starts_with($path, '/notifications')
            || $path === '/' || str_starts_with($path, '/a-propos') || str_starts_with($path, '/contact')) {
            return;
        }

        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if ($path === $prefix || str_starts_with($path, rtrim($prefix, '/').'/') || $path === rtrim($prefix, '/')) {
                $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_dashboard')));

                return;
            }
        }

        // Accueil /
        if ($path === '/') {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('admin_dashboard')));
        }
    }
}
