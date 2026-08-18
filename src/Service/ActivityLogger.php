<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Shop;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
    ) {
    }

    public function log(string $action, string $description, ?User $user = null, ?Shop $shop = null): void
    {
        $log = new ActivityLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setUser($user);
        $log->setShop($shop);

        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $log->setIpAddress($request->getClientIp());
        }

        $this->em->persist($log);
    }
}
