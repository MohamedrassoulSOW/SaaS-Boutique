<?php

namespace App\Entity;

use App\Repository\SaleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SaleRepository::class)]
#[ORM\Table(name: 'sales', indexes: [
    new ORM\Index(columns: ['shop_id', 'status', 'sold_at']),
    new ORM\Index(columns: ['customer_id', 'sold_at']),
    new ORM\Index(columns: ['sold_by_id']),
])]
class Sale
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_CASH = 'cash';
    public const PAYMENT_CARD = 'card';
    public const PAYMENT_MOBILE = 'mobile';
    public const PAYMENT_CREDIT = 'credit';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Version]
    private int $version;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Shop $shop = null;

    #[ORM\ManyToOne(inversedBy: 'sales')]
    private ?Customer $customer = null;

    #[ORM\Column(length: 40)]
    private ?string $reference = null;

    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_COMPLETED;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $subtotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $discount = '0.00';

    /** Taux TVA appliqué au moment de la vente (%) */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $taxRate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $taxAmount = '0.00';

    /** true si les prix saisis étaient TTC */
    #[ORM\Column(options: ['default' => true])]
    private bool $pricesIncludeTax = true;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $total = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    private string $amountPaid = '0.00';

    #[ORM\Column(length: 30)]
    private string $paymentMethod = self::PAYMENT_CASH;

    #[ORM\Column]
    private \DateTimeImmutable $soldAt;

    #[ORM\ManyToOne]
    private ?User $soldBy = null;

    /** @var Collection<int, SaleItem> */
    #[ORM\OneToMany(mappedBy: 'sale', targetEntity: SaleItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\OneToOne(mappedBy: 'sale', cascade: ['persist', 'remove'])]
    private ?Invoice $invoice = null;

    public function __construct()
    {
        $this->soldAt = new \DateTimeImmutable();
        $this->items = new ArrayCollection();
        $this->reference = 'VTE-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): static
    {
        $this->shop = $shop;

        return $this;
    }

    public function getCustomer(): ?Customer
    {
        return $this->customer;
    }

    public function setCustomer(?Customer $customer): static
    {
        $this->customer = $customer;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): static
    {
        $this->reference = $reference;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSubtotal(): string
    {
        return $this->subtotal;
    }

    public function setSubtotal(string $subtotal): static
    {
        $this->subtotal = $subtotal;

        return $this;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function setDiscount(string $discount): static
    {
        $this->discount = $discount;

        return $this;
    }

    public function getTaxRate(): string
    {
        return $this->taxRate;
    }

    public function setTaxRate(string $taxRate): static
    {
        $this->taxRate = $taxRate;

        return $this;
    }

    public function getTaxAmount(): string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(string $taxAmount): static
    {
        $this->taxAmount = $taxAmount;

        return $this;
    }

    public function isPricesIncludeTax(): bool
    {
        return $this->pricesIncludeTax;
    }

    public function setPricesIncludeTax(bool $pricesIncludeTax): static
    {
        $this->pricesIncludeTax = $pricesIncludeTax;

        return $this;
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    public function getAmountPaid(): string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(string $amountPaid): static
    {
        $this->amountPaid = $amountPaid;

        return $this;
    }

    public function getPaymentMethod(): string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): static
    {
        $this->paymentMethod = $paymentMethod;

        return $this;
    }

    public function getSoldAt(): \DateTimeImmutable
    {
        return $this->soldAt;
    }

    public function setSoldAt(\DateTimeImmutable $soldAt): static
    {
        $this->soldAt = $soldAt;

        return $this;
    }

    public function getSoldBy(): ?User
    {
        return $this->soldBy;
    }

    public function setSoldBy(?User $soldBy): static
    {
        $this->soldBy = $soldBy;

        return $this;
    }

    /** @return Collection<int, SaleItem> */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(SaleItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setSale($this);
        }

        return $this;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(?Invoice $invoice): static
    {
        if ($invoice === null && $this->invoice !== null) {
            $this->invoice->setSale(null);
        }

        if ($invoice !== null && $invoice->getSale() !== $this) {
            $invoice->setSale($this);
        }

        $this->invoice = $invoice;

        return $this;
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0.0;
        foreach ($this->items as $item) {
            $subtotal += (float) $item->getUnitPrice() * $item->getQuantity();
        }
        $this->subtotal = number_format($subtotal, 2, '.', '');
        $afterDiscount = max(0, $subtotal - (float) $this->discount);
        $rate = (float) $this->taxRate;

        if ($rate <= 0) {
            $this->taxAmount = '0.00';
            $this->total = number_format($afterDiscount, 2, '.', '');

            return;
        }

        if ($this->pricesIncludeTax) {
            $gross = round($afterDiscount, 2);
            $net = round($gross / (1 + $rate / 100), 2);
            $tax = round($gross - $net, 2);
            $this->taxAmount = number_format($tax, 2, '.', '');
            $this->total = number_format($gross, 2, '.', '');
        } else {
            $net = round($afterDiscount, 2);
            $tax = round($net * $rate / 100, 2);
            $this->taxAmount = number_format($tax, 2, '.', '');
            $this->total = number_format($net + $tax, 2, '.', '');
        }
    }

    public function isPaid(): bool
    {
        return (float) $this->amountPaid >= (float) $this->total;
    }
}
