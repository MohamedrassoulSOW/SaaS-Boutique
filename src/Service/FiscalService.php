<?php

namespace App\Service;

use App\Entity\PlatformFiscalSettings;
use App\Entity\Shop;
use Doctrine\ORM\EntityManagerInterface;

class FiscalService
{
    public function __construct(
        private EntityManagerInterface $em,
        /** @var array<string, mixed> */
        private array $platform,
    ) {
    }

    public function getPlatformSettings(): PlatformFiscalSettings
    {
        $repo = $this->em->getRepository(PlatformFiscalSettings::class);
        $settings = $repo->find(1);
        if ($settings) {
            return $settings;
        }

        $settings = new PlatformFiscalSettings();
        $settings->setLegalName((string) ($this->platform['legal_name'] ?? 'NdamStore SARL'));
        $settings->setTaxId($this->platform['tax_id'] ?? null);
        $settings->setAddress($this->platform['address'] ?? null);
        $settings->setCity($this->platform['city'] ?? null);
        $settings->setCountry($this->platform['country'] ?? 'Sénégal');
        $settings->setEmail($this->platform['email'] ?? null);
        $settings->setPhone($this->platform['phone'] ?? null);
        $settings->setDefaultVatRate('18.00');
        $settings->setDefaultPricesIncludeTax(true);
        $settings->setTaxOnSubscriptions(true);
        $this->em->persist($settings);
        $this->em->flush();

        return $settings;
    }

    /**
     * @return array{enabled: bool, rate: float, pricesIncludeTax: bool}
     */
    public function resolveShopTax(Shop $shop): array
    {
        $platform = $this->getPlatformSettings();
        $enabled = $shop->isTaxEnabled();
        $rate = $shop->getVatRate();
        if ($rate === null || $rate === '') {
            $rate = $platform->getDefaultVatRate();
        }

        return [
            'enabled' => $enabled,
            'rate' => $enabled ? (float) $rate : 0.0,
            'pricesIncludeTax' => $shop->isPricesIncludeTax(),
        ];
    }

    /**
     * @return array{net: float, tax: float, gross: float}
     */
    public function splitAmount(float $amountAfterDiscount, float $rate, bool $pricesIncludeTax): array
    {
        if ($rate <= 0 || $amountAfterDiscount <= 0) {
            return [
                'net' => round($amountAfterDiscount, 2),
                'tax' => 0.0,
                'gross' => round($amountAfterDiscount, 2),
            ];
        }

        if ($pricesIncludeTax) {
            $gross = round($amountAfterDiscount, 2);
            $net = round($gross / (1 + $rate / 100), 2);
            $tax = round($gross - $net, 2);

            return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
        }

        $net = round($amountAfterDiscount, 2);
        $tax = round($net * $rate / 100, 2);
        $gross = round($net + $tax, 2);

        return ['net' => $net, 'tax' => $tax, 'gross' => $gross];
    }
}
