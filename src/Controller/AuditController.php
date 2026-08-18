<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\ShopMemberRepository;
use App\Service\ShopContext;
use Symfony\Component\HttpFoundation\Request;
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
    public function index(
        Request $request,
        ShopContext $shopContext,
        ActivityLogRepository $logRepo,
        ShopMemberRepository $memberRepo,
    ): Response {
        $shop = $this->requireShop($shopContext);

        $search = $request->query->get('q');
        $action = $request->query->get('action');
        $userId = $request->query->getInt('user');
        $dateFrom = $request->query->get('from');
        $dateTo = $request->query->get('to');
        $page = max(1, $request->query->getInt('page', 1));

        $hasFilters = $search !== '' || $action !== '' || $userId > 0 || $dateFrom !== '' || $dateTo !== '';

        $result = $logRepo->searchForShop(
            $shop,
            $search ?: null,
            $action ?: null,
            $userId > 0 ? $userId : null,
            $dateFrom ?: null,
            $dateTo ?: null,
            $page,
            50,
        );

        return $this->render('audit/index.html.twig', [
            'shop' => $shop,
            'logs' => $result['logs'],
            'total' => $result['total'],
            'page' => $page,
            'perPage' => 50,
            'totalPages' => (int) ceil($result['total'] / 50),
            'actions' => $logRepo->findDistinctActionsForShop($shop),
            'members' => $memberRepo->findByShop($shop),
            'filters' => [
                'q' => $search,
                'action' => $action,
                'user' => $userId > 0 ? $userId : null,
                'from' => $dateFrom,
                'to' => $dateTo,
            ],
            'hasFilters' => $hasFilters,
        ]);
    }
}
