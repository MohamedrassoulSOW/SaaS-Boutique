<?php

namespace App\Entity;

use App\Repository\PurchaseOrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PurchaseOrderItemRepository::class)]
#[ORM\Table(name: 'purchase_order_items')]
#[ORM\Index(name: 'IDX_POI_ORDER', columns: ['purchase_order_id'])]
#[ORM\Index(name: 'IDX_POI_PRODUCT', columns: ['product_id'])]
class PurchaseOrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PurchaseOrder $purchaseOrder = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(options: ['default' => 0])]
    private int $receivedQuantity = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $unitPrice = '0.00';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPurchaseOrder(): ?PurchaseOrder
    {
        return $this->purchaseOrder;
    }

    public function setPurchaseOrder(?PurchaseOrder $purchaseOrder): static
    {
        $this->purchaseOrder = $purchaseOrder;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): static
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getReceivedQuantity(): int
    {
        return $this->receivedQuantity;
    }

    public function setReceivedQuantity(int $receivedQuantity): static
    {
        $this->receivedQuantity = max(0, $receivedQuantity);

        return $this;
    }

    public function getRemainingQuantity(): int
    {
        return max(0, $this->quantity - $this->receivedQuantity);
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getLineTotal(): float
    {
        return (float) $this->unitPrice * $this->quantity;
    }
}
