<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ContractService
{
    /**
     * @param array{
     *   name: string,
     *   legal_name: string,
     *   address: string,
     *   city: string,
     *   country: string,
     *   tax_id: string,
     *   email: string,
     *   phone: string,
     *   representative: string
     * } $platform
     */
    public function __construct(
        private EntityManagerInterface $em,
        private Environment $twig,
        private ActivityLogger $activityLogger,
        private array $platform,
    ) {
    }

    public function getPlatform(): array
    {
        return $this->platform;
    }

    public function createForShop(
        Shop $shop,
        User $admin,
        string $plan,
        string $price,
        int $durationMonths,
        string $billingPeriod = ShopContract::BILLING_MONTHLY,
    ): ShopContract {
        $merchant = $shop->getMerchant();
        if (!$merchant) {
            throw new \InvalidArgumentException('Boutique sans commerçant.');
        }

        if (!\in_array($billingPeriod, [ShopContract::BILLING_MONTHLY, ShopContract::BILLING_ANNUAL], true)) {
            $billingPeriod = ShopContract::BILLING_MONTHLY;
        }

        $starts = new \DateTimeImmutable();
        $ends = $starts->modify(sprintf('+%d months', max(1, $durationMonths)));

        $subscription = $merchant->getSubscription();
        if (!$subscription) {
            $subscription = new Subscription();
            $subscription->setMerchant($merchant);
            $merchant->setSubscription($subscription);
            $this->em->persist($subscription);
        }
        $subscription->setPlan($plan);
        $subscription->setPrice(number_format((float) $price, 2, '.', ''));
        $subscription->setBillingPeriod($billingPeriod);
        $subscription->setStatus(Subscription::STATUS_ACTIVE);
        $subscription->setStartsAt($starts);
        $subscription->setEndsAt($ends);
        $subscription->setNextDueAt(
            $billingPeriod === ShopContract::BILLING_ANNUAL
                ? $starts->modify('+1 year')
                : $starts->modify('+1 month')
        );

        $contract = $shop->getContract() ?? new ShopContract();
        $contract->setShop($shop);
        $contract->setMerchant($merchant);
        $contract->setCreatedBy($admin);
        $contract->setPlan($plan);
        $contract->setPrice(number_format((float) $price, 2, '.', ''));
        $contract->setBillingPeriod($billingPeriod);
        $contract->setDurationMonths($durationMonths);
        $contract->setStartsAt($starts);
        $contract->setEndsAt($ends);
        $contract->setStatus(ShopContract::STATUS_PENDING);
        $contract->setSharedWithMerchant(true);
        $contract->setProposedShopName($shop->getName());
        $contract->setProposedShopAddress($shop->getAddress());
        $contract->setProposedShopPhone($shop->getPhone());
        $contract->setProposedShopEmail($shop->getEmail());
        $shop->setContract($contract);

        $this->em->persist($contract);
        $this->em->flush();

        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.create',
            sprintf('Contrat %s généré pour %s (%s)', $contract->getNumber(), $shop->getName(), $contract->getBillingPeriodLabel()),
            $admin,
            $shop
        );

        return $contract;
    }

    public function saveDraft(ShopContract $contract, User $admin, bool $share = false): ShopContract
    {
        if (!$contract->getMerchant()) {
            throw new \InvalidArgumentException('Commerçant obligatoire.');
        }

        $duration = max(1, $contract->getDurationMonths());
        $starts = $contract->getStartsAt() ?: new \DateTimeImmutable();
        $contract->setStartsAt($starts);
        $contract->setEndsAt($starts->modify(sprintf('+%d months', $duration)));
        $contract->setDurationMonths($duration);
        $contract->setPrice(number_format((float) $contract->getPrice(), 2, '.', ''));
        $contract->setCreatedBy($admin);

        if ($contract->getShop()) {
            $shop = $contract->getShop();
            if ($shop->getMerchant()?->getId() !== $contract->getMerchant()->getId()) {
                throw new \InvalidArgumentException('La boutique ne correspond pas au commerçant.');
            }
            if ($shop->getContract() && $shop->getContract()->getId() !== $contract->getId()) {
                throw new \InvalidArgumentException('Cette boutique a déjà un autre contrat.');
            }
            $shop->setContract($contract);
            $contract->setProposedShopName($shop->getName());
            $contract->setProposedShopAddress($shop->getAddress());
            $contract->setProposedShopPhone($shop->getPhone());
            $contract->setProposedShopEmail($shop->getEmail());
        }

        if ($contract->getStatus() !== ShopContract::STATUS_SIGNED
            && $contract->getStatus() !== ShopContract::STATUS_PENDING) {
            $contract->setStatus(ShopContract::STATUS_DRAFT);
        }

        if ($share) {
            $contract->setSharedWithMerchant(true);
        }

        $this->em->persist($contract);
        $this->em->flush();
        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.draft',
            sprintf('Contrat discussion %s — %s', $contract->getNumber(), $contract->getDisplayShopName()),
            $admin,
            $contract->getShop()
        );

        return $contract;
    }

    public function shareWithMerchant(ShopContract $contract, User $admin): void
    {
        $contract->setSharedWithMerchant(true);
        if ($contract->isDraft()) {
            // reste en draft = "En discussion" côté commerçant
        }
        $this->em->flush();
        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.share',
            sprintf('Contrat %s partagé avec le commerçant', $contract->getNumber()),
            $admin,
            $contract->getShop()
        );
    }

    public function sendForSignature(ShopContract $contract, User $admin): void
    {
        $contract->setSharedWithMerchant(true);
        $contract->setStatus(ShopContract::STATUS_PENDING);
        $contract->setPlatformSignedAt(null);
        $contract->setPlatformSignedBy(null);
        $contract->setMerchantSignedAt(null);
        $contract->setMerchantSignedBy(null);
        $contract->setMerchantSignedTitle(null);
        $this->em->flush();
        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.send_signature',
            sprintf('Contrat %s envoyé pour signature', $contract->getNumber()),
            $admin,
            $contract->getShop()
        );
    }

    public function generatePdf(ShopContract $contract): string
    {
        $html = $this->twig->render('contract/pdf.html.twig', [
            'contract' => $contract,
            'shop' => $contract->getShop(),
            'merchant' => $contract->getMerchant(),
            'user' => $contract->getMerchant()?->getUser(),
            'platform' => $this->platform,
            'for_print' => false,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4');
        $dompdf->render();
        $pdf = $dompdf->output();

        $contract->setPdfData($pdf);
        $this->em->flush();

        return $pdf;
    }

    public function signPlatform(ShopContract $contract, string $signerName, User $actor): void
    {
        $contract->setPlatformSignedBy(trim($signerName));
        $contract->setPlatformSignedAt(new \DateTimeImmutable());
        $contract->refreshSignedStatus();
        $this->em->flush();
        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.sign_platform',
            sprintf('Signature plateforme sur %s', $contract->getNumber()),
            $actor,
            $contract->getShop()
        );
    }

    public function signMerchant(ShopContract $contract, string $signerName, string $title, User $actor): void
    {
        $contract->setMerchantSignedBy(trim($signerName));
        $contract->setMerchantSignedTitle(trim($title));
        $contract->setMerchantSignedAt(new \DateTimeImmutable());
        $contract->refreshSignedStatus();
        $this->em->flush();
        $this->generatePdf($contract);

        $this->activityLogger->log(
            'contract.sign_merchant',
            sprintf('Signature commerçant sur %s', $contract->getNumber()),
            $actor,
            $contract->getShop()
        );
    }
}
