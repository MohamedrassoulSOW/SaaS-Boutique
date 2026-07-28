<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Shop;
use App\Entity\StockMovement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class SaleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockService $stockService,
        private ActivityLogger $activityLogger,
        private InvoicePdfService $invoicePdfService,
    ) {
    }

    /**
     * @param array<int, array{product_id: int, quantity: int, unit_price?: string}> $lines
     */
    public function createSale(
        Shop $shop,
        User $user,
        array $lines,
        float $discount = 0,
        string $paymentMethod = Sale::PAYMENT_CASH,
        float $amountPaid = 0,
        ?\App\Entity\Customer $customer = null,
    ): Sale {
        $sale = new Sale();
        $sale->setShop($shop);
        $sale->setSoldBy($user);
        $sale->setDiscount(number_format($discount, 2, '.', ''));
        $sale->setPaymentMethod($paymentMethod);
        $sale->setCustomer($customer);

        $productRepo = $this->em->getRepository(\App\Entity\Product::class);

        foreach ($lines as $line) {
            $product = $productRepo->find($line['product_id']);
            if (!$product || $product->getShop()?->getId() !== $shop->getId()) {
                throw new \InvalidArgumentException('Produit invalide.');
            }

            $qty = (int) $line['quantity'];
            if ($qty < 1) {
                continue;
            }
            if ($product->getQuantity() < $qty) {
                throw new \RuntimeException(sprintf('Stock insuffisant pour "%s".', $product->getName()));
            }

            $item = new SaleItem();
            $item->setProduct($product);
            $item->setQuantity($qty);
            $item->setUnitPrice($line['unit_price'] ?? $product->getSalePrice());
            $sale->addItem($item);

            $this->stockService->adjust(
                $product,
                -$qty,
                StockMovement::TYPE_SALE,
                $user,
                'Vente '.$sale->getReference()
            );
        }

        $sale->recalculateTotals();
        $paid = $amountPaid > 0 ? $amountPaid : (float) $sale->getTotal();
        $sale->setAmountPaid(number_format($paid, 2, '.', ''));

        if ($customer && $paymentMethod === Sale::PAYMENT_CREDIT) {
            $due = (float) $sale->getTotal() - $paid;
            if ($due > 0) {
                $customer->setBalance(number_format((float) $customer->getBalance() + $due, 2, '.', ''));
            }
        }

        $invoice = new Invoice();
        $invoice->setSale($sale);
        $invoice->setType(Invoice::TYPE_INVOICE);
        $sale->setInvoice($invoice);

        $this->em->persist($sale);
        $this->em->flush();

        // PDF facture stocké en base (pas de fichier disque)
        $this->invoicePdfService->generate($sale);

        $this->activityLogger->log('sale.create', 'Vente '.$sale->getReference(), $user, $shop);

        return $sale;
    }
}