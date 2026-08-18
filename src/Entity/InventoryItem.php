<?php

namespace App\Entity;

use App\Repository\InventoryItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InventoryItemRepository::class)]
#[ORM\Table(name: 'inventory_items')]
#[ORM\Index(name: 'IDX_INVITEM_INVENTORY', columns: ['inventory_id'])]
#[ORM\Index(name: 'IDX_INVITEM_PRODUCT', columns: ['product_id'])]
class InventoryItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Inventory $inventory = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Product $product = null;

    #[ORM\Column]
    private int $theoreticalQty = 0;

    #[ORM\Column]
    private int $realQty = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInventory(): ?Inventory
    {
        return $this->inventory;
    }

    public function setInventory(?Inventory $inventory): static
    {
        $this->inventory = $inventory;

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

    public function getTheoreticalQty(): int
    {
        return $this->theoreticalQty;
    }

    public function setTheoreticalQty(int $theoreticalQty): static
    {
        $this->theoreticalQty = $theoreticalQty;

        return $this;
    }

    public function getRealQty(): int
    {
        return $this->realQty;
    }

    public function setRealQty(int $realQty): static
    {
        $this->realQty = $realQty;

        return $this;
    }

    public function getDifference(): int
    {
        return $this->realQty - $this->theoreticalQty;
    }
}
