<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Génère un nonce CSP unique par requête et le stocke dans les request attributes
 * pour utilisation dans les templates Twig et les headers CSP.
 */
class CspNonceListener implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 8]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $nonce = bin2hex(random_bytes(16));
        $event->getRequest()->attributes->set('csp_nonce', $nonce);
    }
}
