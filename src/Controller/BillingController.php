<?php

namespace App\Controller;

use App\Entity\Subscription;
use App\Service\ShopContext;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/facturation')]
#[IsGranted('MODULE_BILLING')]
class BillingController extends ShopAwareController
{
    #[Route('', name: 'app_billing_index')]
    public function index(ShopContext $shopContext): Response
    {
        $shop = $this->requireShop($shopContext);
        $merchant = $shop->getMerchant();
        $subscription = $merchant?->getSubscription();

        return $this->render('billing/index.html.twig', [
            'shop' => $shop,
            'merchant' => $merchant,
            'subscription' => $subscription,
            'planLabel' => $subscription ? Subscription::planLabel($subscription->getPlan()) : '—',
            'catalogBasic' => Subscription::catalogPrice(Subscription::PLAN_BASIC),
            'catalogPro' => Subscription::catalogPrice(Subscription::PLAN_PRO),
        ]);
    }
}
