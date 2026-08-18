<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(private UrlGeneratorInterface $urlGenerator)
    {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $session = $request->getSession();
        $from = $request->attributes->get('_route');
        $loopKey = 'access_denied_loop';

        $flash = $session instanceof FlashBagAwareSessionInterface ? $session->getFlashBag() : null;

        // Évite la boucle dashboard ↔ shops pour les employés sans entreprise active
        if (\in_array($from, ['app_dashboard', 'app_shop_index', 'app_shop_switch'], true)
            || $session->get($loopKey) === $from
        ) {
            $session->remove($loopKey);
            $flash?->add(
                'warning',
                'Aucune entreprise accessible pour votre compte. Contactez l\'administrateur ou reconnectez-vous.'
            );

            return new RedirectResponse($this->urlGenerator->generate('app_profile'));
        }

        $session->set($loopKey, $from ?: 'unknown');
        $flash?->add(
            'warning',
            'Vous n\'avez pas accès à cette section avec votre rôle actuel.'
        );

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }
}
