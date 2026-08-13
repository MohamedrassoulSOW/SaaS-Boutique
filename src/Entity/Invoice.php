<?php

namespace App\Entity;

use App\Repository\InvoiceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'invoices')]
class Invoice
{
    use BinaryPayloadTrait;

    public const TYPE_INVOICE = 'invoice';
    public const TYPE_RECEIPT = 'receipt';
    public const TYPE_DELIVERY = 'delivery';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'invoice')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Sale $sale = null;

    #[ORM\Column(length: 40)]
    private ?string $number = null;

    #[ORM\Column(length: 30)]
    private string $type = self::TYPE_INVOICE;

    #[ORM\Column]
    private \DateTimeImmutable $issuedAt;

    /** PDF généré stocké en base */
    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private mixed $pdfData = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pdfMime = 'application/pdf';

    public function __construct()
    {
        $this->issuedAt = new \DateTimeImmutable();
        // Numéro attribué lors de la persistance (séquence boutique)
        $this->number = 'FAC-TMP-'.substr(uniqid(), -6);
    }

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

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIssuedAt(): \DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function getPdfData(): ?string
    {
        if (\is_resource($this->pdfData)) {
            $this->pdfData = $this->readBinary($this->pdfData);
        }

        return $this->readBinary($this->pdfData);
    }

    public function setPdfData(?string $pdfData): static
    {
        $this->pdfData = $this->writeBinary($pdfData);
        $this->pdfMime = 'application/pdf';

        return $this;
    }

    public function getPdfMime(): ?string
    {
        return $this->pdfMime;
    }

    public function hasPdf(): bool
    {
        return $this->getPdfData() !== null;
    }
}
