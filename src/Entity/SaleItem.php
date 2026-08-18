<?php

namespace App\Entity;

use App\Repository\SaleItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SaleItemRepository::class)]
#[ORM\Table(name: 'sale_items')]
#[ORM\Index(name: 'IDX_SALEITEM_SALE', columns: ['sale_id'])]
#[ORM\Index(name: 'IDX_SALEITEM_PRODUCT', columns: ['product_id'])]
class SaleItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sale $sale = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private int $quantity = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $unitPrice = '0.00';

    /** Coût d'achat figé au moment de la vente (pour calcul de marge). */
    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, nullable: true)]
    private ?string $unitCost = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSale(): ?Sale
    {
        return $this->sale;
    }

    public function setSale(?Sale $sale): static
    {
        $this->sale = $sale;

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

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getUnitCost(): ?string
    {
        return $this->unitCost;
    }

    public function setUnitCost(?string $unitCost): static
    {
        $this->unitCost = $unitCost;

        return $this;
    }

    public function getLineTotal(): float
    {
        return (float) $this->unitPrice * $this->quantity;
    }

    public function getLineCost(): float
    {
        return (float) ($this->unitCost ?? 0) * $this->quantity;
    }

    public function getLineProfit(): float
    {
        return $this->getLineTotal() - $this->getLineCost();
    }
}
