<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Service\ShopContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Journal d'audit de l'entreprise courante (traçabilité des actions).
 */
#[Route('/audit')]
#[IsGranted('MODULE_REPORTS')]
class AuditController extends ShopAwareController
{
    #[Route('', name: 'app_audit_index')]
    public function index(ShopContext $shopContext, ActivityLogRepository $logs): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('audit/index.html.twig', [
            'shop' => $shop,
            'logs' => $logs->findRecentForShop($shop, 300),
        ]);
    }
}
