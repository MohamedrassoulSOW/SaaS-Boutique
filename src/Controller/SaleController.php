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
            /** @var User $user */
            $user = $this->getUser();
            $productIds = $request->request->all('product_id') ?: [];
            $quantities = $request->request->all('quantity') ?: [];
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
                $this->addFlash('danger', 'Ajoutez au moins un produit.');
            } else {
                try {
                    $customerId = $request->request->getInt('customer_id') ?: null;
                    $customer = $customerId ? $customers->find($customerId) : null;
                    if ($customer) {
                        $this->assertShopData($shopContext, $customer->getShop());
                    }
                    $sale = $saleService->createSale(
                        $shop,
                        $user,
                        $lines,
                        (float) $request->request->get('discount', 0),
                        (string) $request->request->get('payment_method', Sale::PAYMENT_CASH),
                        (float) $request->request->get('amount_paid', 0),
                        $customer,
                    );
                    $this->addFlash('success', 'Vente enregistrée : '.$sale->getReference());

                    return $this->redirectToRoute('app_sale_show', ['id' => $sale->getId()]);
                } catch (\Throwable $e) {
                    $this->addFlash('danger', $e->getMessage());
                }
            }
        }

        return $this->render('sale/new.html.twig', [
            'products' => $productList,
            'customers' => $customerList,
            'taxConfig' => $taxConfig,
        ]);
    }

    #[Route('/{id}', name: 'app_sale_show')]
    public function show(Sale $sale, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        return $this->render('sale/show.html.twig', ['sale' => $sale]);
    }

    #[Route('/{id}/invoice.pdf', name: 'app_sale_invoice_pdf')]
    public function invoicePdf(Sale $sale, ShopContext $shopContext, InvoicePdfService $pdf): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        $content = $pdf->generate($sale);
        $number = $sale->getInvoice()?->getNumber() ?? $sale->getReference();

        return new Response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$number.'.pdf"',
        ]);
    }

    #[Route('/{id}/print', name: 'app_sale_print')]
    public function print(Sale $sale, ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->assertShopData($shopContext, $sale->getShop());

        return $this->render('sale/print.html.twig', ['sale' => $sale]);
    }
}
