<?php

namespace App\Entity;

use App\Repository\PlatformFiscalSettingsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paramètres fiscaux de la plateforme (singleton id = 1).
 */
#[ORM\Entity(repositoryClass: PlatformFiscalSettingsRepository::class)]
#[ORM\Table(name: 'platform_fiscal_settings')]
class PlatformFiscalSettings
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    private string $legalName = 'NdamStore SARL';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $taxId = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $registrationNumber = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $legalForm = 'SARL';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $country = 'Sénégal';

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $phone = null;

    /** Taux TVA par défaut pour les boutiques (%) */
    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private string $defaultVatRate = '18.00';

    /** Les prix catalogue boutique sont en TTC par défaut */
    #[ORM\Column]
    private bool $defaultPricesIncludeTax = true;

    /** Appliquer la TVA sur les factures d'abonnement plateforme */
    #[ORM\Column]
    private bool $taxOnSubscriptions = true;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getLegalName(): string
    {
        return $this->legalName;
    }

    public function setLegalName(string $legalName): static
    {
        $this->legalName = $legalName;

        return $this;
    }

    public function getTaxId(): ?string
    {
        return $this->taxId;
    }

    public function setTaxId(?string $taxId): static
    {
        $this->taxId = $taxId;

        return $this;
    }

    public function getRegistrationNumber(): ?string
    {
        return $this->registrationNumber;
    }

    public function setRegistrationNumber(?string $registrationNumber): static
    {
        $this->registrationNumber = $registrationNumber;

        return $this;
    }

    public function getLegalForm(): ?string
    {
        return $this->legalForm;
    }

    public function setLegalForm(?string $legalForm): static
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): static
    {
        $this->country = $country;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function getDefaultVatRate(): string
    {
        return $this->defaultVatRate;
    }

    public function setDefaultVatRate(string $defaultVatRate): static
    {
        $this->defaultVatRate = $defaultVatRate;

        return $this;
    }

    public function isDefaultPricesIncludeTax(): bool
    {
        return $this->defaultPricesIncludeTax;
    }

    public function setDefaultPricesIncludeTax(bool $defaultPricesIncludeTax): static
    {
        $this->defaultPricesIncludeTax = $defaultPricesIncludeTax;

        return $this;
    }

    public function isTaxOnSubscriptions(): bool
    {
        return $this->taxOnSubscriptions;
    }

    public function setTaxOnSubscriptions(bool $taxOnSubscriptions): static
    {
        $this->taxOnSubscriptions = $taxOnSubscriptions;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): static
    {
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }
}
