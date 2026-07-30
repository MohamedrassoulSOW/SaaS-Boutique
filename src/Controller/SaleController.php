<?php

namespace App\Controller;

use App\Entity\Sale;
use App\Entity\User;
use App\Repository\CustomerRepository;
use App\Repository\ProductRepository;
use App\Repository\SaleRepository;
use App\Service\FiscalService;
use App\Service\InvoicePdfService;
use App\Service\SaleService;
use App\Service\ShopContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sales')]
#[IsGranted('MODULE_SALES')]
class SaleController extends ShopAwareController
{
    #[Route('', name: 'app_sale_index')]
    public function index(SaleRepository $repo, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);

        return $this->render('sale/index.html.twig', [
            'sales' => $repo->findBy(['shop' => $shop], ['soldAt' => 'DESC'], 100),
        ]);
    }

    #[Route('/new', name: 'app_sale_new')]
    public function new(
        Request $request,
        ShopContext $shopContext,
        ProductRepository $products,
        CustomerRepository $customers,
        SaleService $saleService,
        FiscalService $fiscal,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $productList = $products->findActiveForPos($shop);
        $customerList = $customers->findBy(['shop' => $shop], ['lastName' => 'ASC']);
        $taxConfig = $fiscal->resolveShopTax($shop);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('sale_new', (string) $request->request->get('_token'))) {
                $this->addFlash('sale_error', 'Session expirée. Rechargez la page et réessayez.');

                return $this->render('sale/new.html.twig', [
                    'products' => $productList,
                    'customers' => $customerList,
                    'taxConfig' => $taxConfig,
                ]);
            }

            /** @var User $user */
            $user = $this->getUser();
            $payload = $request->request->all();
            $productIds = $payload['product_id'] ?? [];
            $quantities = $payload['quantity'] ?? [];
            if (!\is_array($productIds)) {
                $productIds = $productIds !== null && $productIds !== '' ? [$productIds] : [];
            }
            if (!\is_array($quantities)) {
                $quantities = $quantities !== null && $quantities !== '' ? [$quantities] : [];
            }
            $lines = [];
            foreach ($productIds as $i => $productId) {
                $pid = (int) $productId;
                $qty = (int) ($quantities[$i] ?? 0);
                if ($pid < 1 || $qty < 1) {
                    continue;
                }
                // Fusionne les quantités si le même produit est répété
                if (isset($lines[$pid])) {
                    $lines[$pid]['quantity'] += $qty;
                } else {
                    $lines[$pid] = ['product_id' => $pid, 'quantity' => $qty];
                }
            }
            $lines = array_values($lines);

            if ($lines === []) {
                $this->addFlash('sale_error', 'Ajoutez au moins un produit au panier avant d\'encaisser.');
            } else {
                try {
                    $customerRaw = $request->request->get('customer_id');
                    $customerId = null;
                    if ($customerRaw !== null && $customerRaw !== '' && ctype_digit((string) $customerRaw)) {
                        $customerId = (int) $customerRaw;
                    }
                    $customer = $customerId ? $customers->find($customerId) : null;
                    if ($customer) {
                        $this->assertShopData($shopContext, $customer->getShop());
                    }
                    $amountPaidRaw = $request->request->get('amount_paid');
                    $amountPaid = ($amountPaidRaw === null || $amountPaidRaw === '')
                        ? null
                        : (float) $amountPaidRaw;

                    $sale = $saleService->createSale(
                        $shop,
                        $user,
                        $lines,
                        (float) ($request->request->get('discount') ?: 0),
                        (string) ($request->request->get('payment_method') ?: Sale::PAYMENT_CASH),
                        $amountPaid,
                        $customer,
                    );
                    $saleService->generateInvoicePdfSafe($sale);
                    $this->addFlash(
                        'sale_success',
                        sprintf(
                            "Vente %s enregistrée.\nTotal : %s FCFA\nPayé : %s FCFA",
                            $sale->getReference(),
                            number_format((float) $sale->getTotal(), 0, ',', ' '),
                            number_format((float) $sale->getAmountPaid(), 0, ',', ' ')
                        )
                    );

                    return $this->redirectToRoute('app_sale_show', ['id' => $sale->getId()]);
                } catch (\InvalidArgumentException|\RuntimeException $e) {
                    $this->addFlash('sale_error', $e->getMessage() ?: 'L\'encaissement a été refusé.');
                } catch (\Throwable $e) {
                    $this->addFlash('sale_error', 'L\'encaissement a échoué. Réessayez ou contactez le support.');
                }
            }
        }

        return $this->render('sale/new.html.twig', [
            'products' => $productList,
            'customers' => $customerList,
            'taxConfig' => $taxConfig,
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_sale_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('MODULE_SALE_CANCEL')]
    public function cancel(
        Sale $sale,
        Request $request,
        ShopContext $shopContext,
        SaleService $saleService,
    ): Response {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());
        if (!$this->isCsrfTokenValid('sale_cancel'.$sale->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Session expirée.');

            return $this->redirectToRoute('app_sale_show', ['id' => $sale->getId()]);
        }

        try {
            $saleService->cancelSale($sale, $this->getShopUser());
            $this->addFlash('success', 'Vente annulée. Stock et crédit client recalculés.');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_sale_show', ['id' => $sale->getId()]);
    }

    #[Route('/{id}', name: 'app_sale_show', requirements: ['id' => '\d+'])]
    public function show(Sale $sale, ShopContext $shopContext): Response
    {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        return $this->render('sale/show.html.twig', ['sale' => $sale]);
    }

    #[Route('/{id}/invoice.pdf', name: 'app_sale_invoice_pdf', requirements: ['id' => '\d+'])]
    public function invoicePdf(Sale $sale, ShopContext $shopContext, InvoicePdfService $pdf): Response
    {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        $content = $pdf->generate($sale);
        $number = $sale->getInvoice()?->getNumber() ?? $sale->getReference();

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$number.'.pdf"',
        ]);
    }

    #[Route('/{id}/print', name: 'app_sale_print', requirements: ['id' => '\d+'])]
    public function print(Sale $sale, ShopContext $shopContext): Response
    {
        $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        return $this->render('sale/print.html.twig', ['sale' => $sale]);
    }
}
