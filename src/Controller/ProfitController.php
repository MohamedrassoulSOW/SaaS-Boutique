<?php

namespace App\Controller;

use App\Service\ProfitService;
use App\Service\ShopContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/benefices')]
#[IsGranted('MODULE_VIEW_MARGIN')]
class ProfitController extends ShopAwareController
{
    #[Route('', name: 'app_profit_index')]
    public function index(Request $request, ShopContext $shopContext, ProfitService $profitService): Response
    {
        $shop = $this->requireShop($shopContext);
        [$from, $toExclusive, $toInclusive, $period, $periodLabel] = $this->resolvePeriod($request);

        $productId = $request->query->get('product');
        $productId = ($productId !== null && $productId !== '') ? (int) $productId : null;
        if ($productId !== null && $productId < 1) {
            $productId = null;
        }

        $sort = (string) $request->query->get('sort', 'profit');
        $q = (string) $request->query->get('q', '');

        $summary = $profitService->summarize($shop, $from, $toExclusive, $productId, $sort, $q);
        $products = $profitService->listProducts($shop);

        return $this->render('profit/index.html.twig', [
            'shop' => $shop,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'from' => $from,
            'to' => $toInclusive,
            'sort' => $sort,
            'q' => $q,
            'productId' => $productId,
            'products' => $products,
            'summary' => $summary,
        ]);
    }

    #[Route('/export.csv', name: 'app_profit_export')]
    public function export(Request $request, ShopContext $shopContext, ProfitService $profitService): StreamedResponse
    {
        $shop = $this->requireShop($shopContext);
        [$from, $toExclusive, $toInclusive] = $this->resolvePeriod($request);

        $productId = $request->query->get('product');
        $productId = ($productId !== null && $productId !== '') ? (int) $productId : null;
        if ($productId !== null && $productId < 1) {
            $productId = null;
        }

        $sort = (string) $request->query->get('sort', 'profit');
        $q = (string) $request->query->get('q', '');
        $summary = $profitService->summarize($shop, $from, $toExclusive, $productId, $sort, $q);

        $filename = sprintf(
            'benefices-%s-%s.csv',
            $from->format('Ymd'),
            $toInclusive->format('Ymd')
        );

        $response = new StreamedResponse(static function () use ($summary, $from, $toInclusive, $shop): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'Entreprise',
                'Du',
                'Au',
                'Produit',
                'Référence',
                'Quantité',
                'CA (FCFA)',
                'Coût (FCFA)',
                'Bénéfice (FCFA)',
                'Marge (%)',
                'Bénéfice / unité (FCFA)',
            ], ';');

            foreach ($summary['byProduct'] as $row) {
                fputcsv($out, [
                    $shop->getName(),
                    $from->format('d/m/Y'),
                    $toInclusive->format('d/m/Y'),
                    $row['name'],
                    $row['reference'] ?? '',
                    (int) $row['qty'],
                    round($row['revenue']),
                    round($row['cost']),
                    round($row['profit']),
                    round($row['margin'], 1),
                    round($row['unitProfit']),
                ], ';');
            }

            fputcsv($out, [], ';');
            fputcsv($out, [
                'TOTAL',
                '',
                '',
                '',
                '',
                $summary['itemsSold'],
                round($summary['revenue']),
                round($summary['cost']),
                round($summary['profit']),
                round($summary['margin'], 1),
                '',
            ], ';');
            fputcsv($out, [
                'DEPENSES',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                round($summary['expenses'] ?? 0),
                '',
                '',
            ], ';');
            fputcsv($out, [
                'BENEFICE_NET',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                round($summary['netProfit'] ?? $summary['profit']),
                '',
                '',
            ], ';');
            fclose($out);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }

    /**
     * @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable, 2: \DateTimeImmutable, 3: string, 4: string}
     */
    private function resolvePeriod(Request $request): array
    {
        $period = (string) $request->query->get('period', 'month');
        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        // Raccourcis : les dates du formulaire sont ignorées (remplies côté JS pour l'affichage)
        if (\in_array($period, ['today', 'week', 'month', 'last_month', 'year'], true)) {
            return match ($period) {
                'today' => [$today, $tomorrow, $today, 'today', "Aujourd'hui"],
                'week' => [$today->modify('-6 days'), $tomorrow, $today, 'week', '7 derniers jours'],
                'last_month' => [
                    new \DateTimeImmutable('first day of last month midnight'),
                    new \DateTimeImmutable('first day of this month midnight'),
                    new \DateTimeImmutable('last day of last month midnight'),
                    'last_month',
                    'Mois précédent',
                ],
                'year' => [
                    new \DateTimeImmutable('first day of January this year midnight'),
                    $tomorrow,
                    $today,
                    'year',
                    'Année en cours',
                ],
                default => [
                    new \DateTimeImmutable('first day of this month midnight'),
                    $tomorrow,
                    $today,
                    'month',
                    'Mois en cours',
                ],
            };
        }

        // Période libre : Du / Au
        $fromRaw = (string) $request->query->get(
            'from',
            (new \DateTimeImmutable('first day of this month midnight'))->format('Y-m-d')
        );
        $toRaw = (string) $request->query->get('to', $today->format('Y-m-d'));

        try {
            $from = new \DateTimeImmutable($fromRaw.' 00:00:00');
        } catch (\Exception) {
            $from = new \DateTimeImmutable('first day of this month midnight');
        }

        try {
            $toInclusive = new \DateTimeImmutable($toRaw.' 00:00:00');
        } catch (\Exception) {
            $toInclusive = $today;
        }

        if ($from > $toInclusive) {
            [$from, $toInclusive] = [$toInclusive, $from];
        }

        // Cap max range to 3 years for safety
        $maxFrom = $toInclusive->modify('-3 years');
        if ($from < $maxFrom) {
            $from = $maxFrom;
        }

        $toExclusive = $toInclusive->modify('+1 day');
        $label = sprintf('Du %s au %s', $from->format('d/m/Y'), $toInclusive->format('d/m/Y'));

        return [$from, $toExclusive, $toInclusive, 'custom', $label];
    }
}
