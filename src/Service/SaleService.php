<?php

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\Sale;
use App\Entity\SaleItem;
use App\Entity\Shop;
use App\Entity\StockMovement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;

class SaleService
{
    public function __construct(
        private EntityManagerInterface $em,
        private StockService $stockService,
        private ActivityLogger $activityLogger,
        private InvoicePdfService $invoicePdfService,
        private FiscalService $fiscalService,
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
        ?float $amountPaid = null,
        ?\App\Entity\Customer $customer = null,
    ): Sale {
        return $this->em->wrapInTransaction(function () use ($shop, $user, $lines, $discount, $paymentMethod, $amountPaid, $customer) {
            $sale = new Sale();
            $sale->setShop($shop);
            $sale->setSoldBy($user);
            $sale->setDiscount(number_format($discount, 2, '.', ''));
            $sale->setPaymentMethod($paymentMethod);
            $sale->setCustomer($customer);

            $tax = $this->fiscalService->resolveShopTax($shop);
            $sale->setTaxRate(number_format($tax['rate'], 2, '.', ''));
            $sale->setPricesIncludeTax($tax['pricesIncludeTax']);

            $productRepo = $this->em->getRepository(\App\Entity\Product::class);

            // 1) Batch-load all products to avoid N+1
            $allProductIds = [];
            foreach ($lines as $line) {
                $allProductIds[] = (int) $line['product_id'];
            }
            $allProductIds = array_unique($allProductIds);
            $productsById = [];
            if ($allProductIds !== []) {
                $loadedProducts = $productRepo->findBy(['id' => $allProductIds]);
                foreach ($loadedProducts as $p) {
                    $productsById[$p->getId()] = $p;
                }
            }

            // 2) Valider toutes les lignes avant toute mutation de stock
            $resolved = [];
            foreach ($lines as $line) {
                $product = $productsById[(int) $line['product_id']] ?? null;
                if (!$product || $product->getShop()?->getId() !== $shop->getId() || !$product->isActive()) {
                    throw new \InvalidArgumentException('Produit invalide.');
                }

                $qty = (int) $line['quantity'];
                if ($qty < 1) {
                    continue;
                }
                if ($product->getQuantity() < $qty) {
                    throw new \RuntimeException(sprintf('Stock insuffisant pour "%s".', $product->getName()));
                }

                $resolved[] = [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $line['unit_price'] ?? $product->getSalePrice(),
                ];
            }

            if ($resolved === []) {
                throw new \InvalidArgumentException('Ajoutez au moins un produit.');
            }

            // 2) Appliquer les lignes + stock (flush différé)
            foreach ($resolved as $row) {
                $product = $row['product'];
                $qty = $row['quantity'];

                $item = new SaleItem();
                $item->setProduct($product);
                $item->setQuantity($qty);
                $item->setUnitPrice($row['unit_price']);
                $item->setUnitCost(number_format((float) $product->getPurchasePrice(), 2, '.', ''));
                $sale->addItem($item);

                $this->stockService->adjust(
                    $product,
                    -$qty,
                    StockMovement::TYPE_SALE,
                    $user,
                    'Vente '.$sale->getReference(),
                    false
                );
            }

            $sale->recalculateTotals();

            // Crédit : 0 encaissé = dette totale ; autres modes : vide/0 = total
            if ($paymentMethod === Sale::PAYMENT_CREDIT) {
                $paid = $amountPaid ?? 0.0;
            } else {
                $paid = ($amountPaid !== null && $amountPaid > 0) ? $amountPaid : (float) $sale->getTotal();
            }
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
            $seq = $shop->nextInvoiceSequence();
            $invoice->setNumber(sprintf('FAC-%s-%05d', date('Y'), $seq));
            $sale->setInvoice($invoice);

            $this->em->persist($sale);
            try {
                $this->em->flush();
            } catch (OptimisticLockException) {
                throw new \RuntimeException('Le stock a été modifié par un autre utilisateur. Veuillez réessayer.');
            }

            $this->activityLogger->log(
                'sale.create',
                sprintf('Vente %s par %s (total %s)', $sale->getReference(), $user->getFullName() ?: $user->getEmail(), $sale->getTotal()),
                $user,
                $shop
            );

            return $sale;
        });
    }

    /**
     * Génère le PDF hors transaction — un échec PDF ne doit pas annuler la vente.
     */
    public function generateInvoicePdfSafe(Sale $sale): void
    {
        try {
            $this->invoicePdfService->generate($sale);
        } catch (\Throwable) {
            // La vente reste valide même si le PDF échoue.
        }
    }

    public function cancelSale(Sale $sale, User $user): Sale
    {
        if ($sale->getStatus() === Sale::STATUS_CANCELLED) {
            throw new \RuntimeException('Cette vente est déjà annulée.');
        }

        return $this->em->wrapInTransaction(function () use ($sale, $user) {
            foreach ($sale->getItems() as $item) {
                $product = $item->getProduct();
                if (!$product) {
                    continue;
                }
                $this->stockService->adjust(
                    $product,
                    $item->getQuantity(),
                    StockMovement::TYPE_ADJUSTMENT,
                    $user,
                    'Annulation vente '.$sale->getReference(),
                    false
                );
            }

            if (
                $sale->getCustomer()
                && $sale->getPaymentMethod() === Sale::PAYMENT_CREDIT
            ) {
                $due = (float) $sale->getTotal() - (float) $sale->getAmountPaid();
                if ($due > 0) {
                    $customer = $sale->getCustomer();
                    $newBalance = max(0, (float) $customer->getBalance() - $due);
                    $customer->setBalance(number_format($newBalance, 2, '.', ''));
                }
            }

            $sale->setStatus(Sale::STATUS_CANCELLED);
            try {
                $this->em->flush();
            } catch (OptimisticLockException) {
                throw new \RuntimeException('La vente a été modifiée par un autre utilisateur. Veuillez réessayer.');
            }

            $this->activityLogger->log(
                'sale.cancel',
                sprintf('Vente %s annulée par %s', $sale->getReference(), $user->getFullName() ?: $user->getEmail()),
                $user,
                $sale->getShop()
            );

            return $sale;
        });
    }
}
