<?php

namespace App\Controller;

use App\Form\ShopFiscalType;
use App\Repository\SaleRepository;
use App\Service\ActivityLogger;
use App\Service\FiscalService;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/fiscalite')]
#[IsGranted('MODULE_FISCAL')]
class FiscalController extends ShopAwareController
{
    #[Route('', name: 'app_fiscal_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ShopContext $shopContext,
        SaleRepository $sales,
        FiscalService $fiscalService,
        EntityManagerInterface $em,
        ActivityLogger $logger,
    ): Response {
        $user = $this->getShopUser();
        $shop = $this->requireShop($shopContext);
        $merchant = $shop->getMerchant();
        $taxConfig = $fiscalService->resolveShopTax($shop);
        $platform = $fiscalService->getPlatformSettings();

        $fromInput = $request->query->getString('from');
        $toInput = $request->query->getString('to');
        try {
            $from = $fromInput !== ''
                ? new \DateTimeImmutable($fromInput.' 00:00:00')
                : new \DateTimeImmutable('first day of this month midnight');
        } catch (\Exception) {
            $from = new \DateTimeImmutable('first day of this month midnight');
        }
        try {
            $toExclusive = $toInput !== ''
                ? (new \DateTimeImmutable($toInput.' 00:00:00'))->modify('+1 day')
                : (new \DateTimeImmutable('first day of next month midnight'));
        } catch (\Exception) {
            $toExclusive = (new \DateTimeImmutable('first day of next month midnight'));
        }

        if ($from >= $toExclusive) {
            $from = new \DateTimeImmutable('first day of this month midnight');
            $toExclusive = new \DateTimeImmutable('first day of next month midnight');
        }

        $periodSummary = $sales->summarizeTaxForShop($shop, $from, $toExclusive);
        $monthStart = new \DateTimeImmutable('first day of this month midnight');
        $monthSummary = $sales->summarizeTaxForShop(
            $shop,
            $monthStart,
            $monthStart->modify('+1 month')
        );
        $taxByMonth = $sales->taxCollectedByMonth($shop, 6);

        $form = $this->createForm(ShopFiscalType::class, $shop);
        $form->get('merchantTaxId')->setData($merchant?->getTaxId());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($merchant) {
                $merchant->setTaxId($form->get('merchantTaxId')->getData());
            }

            $vatRate = $form->get('vatRate')->getData();
            $shop->setVatRate($vatRate === null || $vatRate === '' ? null : number_format((float) $vatRate, 2, '.', ''));

            $em->flush();
            $logger->log('shop.fiscal_update', 'Mise à jour fiscale '.$shop->getName(), $user, $shop);
            $this->addFlash('success', 'Paramètres fiscaux enregistrés.');

            return $this->redirectToRoute('app_fiscal_index', [
                'from' => $from->format('Y-m-d'),
                'to' => $toExclusive->modify('-1 day')->format('Y-m-d'),
            ]);
        }

        return $this->render('fiscal/index.html.twig', [
            'shop' => $shop,
            'merchant' => $merchant,
            'taxConfig' => $taxConfig,
            'platformVatRate' => (float) $platform->getDefaultVatRate(),
            'form' => $form,
            'from' => $from,
            'to' => $toExclusive->modify('-1 day'),
            'periodSummary' => $periodSummary,
            'monthSummary' => $monthSummary,
            'taxByMonth' => $taxByMonth,
        ]);
    }
}
