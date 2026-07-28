<?php

namespace App\Controller;

use App\Entity\Shop;
use App\Form\ContractSignType;
use App\Form\ShopType;
use App\Security\Voter\ShopVoter;
use App\Service\ActivityLogger;
use App\Service\BinaryUploadService;
use App\Service\ContractService;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/shops')]
#[IsGranted('MODULE_SHOPS')]
class ShopController extends ShopAwareController
{
    #[Route('', name: 'app_shop_index')]
    public function index(ShopContext $shopContext): Response
    {
        $user = $this->getShopUser();
        if ($user->isAdmin()) {
            return $this->redirectToRoute('admin_shops');
        }

        return $this->render('shop/index.html.twig', [
            'shops' => $shopContext->getAccessibleShops($user),
            'current' => $shopContext->getCurrentShop($user),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_shop_edit')]
    public function edit(
        Shop $shop,
        Request $request,
        EntityManagerInterface $em,
        ShopContext $shopContext,
        ActivityLogger $logger,
        BinaryUploadService $uploader,
    ): Response {
        $this->denyAccessUnlessGranted(ShopVoter::EDIT, $shop);
        $user = $this->getShopUser();

        $form = $this->createForm(ShopType::class, $shop);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($user->isMerchant() && $shop->getMerchant()?->getId() !== $user->getMerchant()?->getId()) {
                throw $this->createAccessDeniedException();
            }

            $file = $form->get('logoFile')->getData();
            if ($file) {
                try {
                    $payload = $uploader->readImage($file);
                    $shop->setLogoData($payload['data']);
                    $shop->setLogoMime($payload['mime']);
                    $shop->setLogoName($payload['name']);
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('danger', $e->getMessage());
                }
            }

            $em->flush();
            $logger->log('shop.update', 'Modification boutique '.$shop->getName(), $user, $shop);
            $this->addFlash('success', 'Boutique mise à jour (logo en base si fourni).');

            return $this->redirectToRoute('app_shop_index');
        }

        return $this->render('shop/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la boutique',
            'shop' => $shop,
        ]);
    }

    #[Route('/{id}/contrat', name: 'app_shop_contract')]
    #[IsGranted('ROLE_MERCHANT')]
    public function contract(
        Shop $shop,
        Request $request,
        ContractService $contracts,
    ): Response {
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);
        $contract = $shop->getContract();
        if (!$contract) {
            throw $this->createNotFoundException('Aucun contrat pour cette boutique.');
        }

        $form = null;
        if ($contract->getMerchantSignedAt() === null) {
            $form = $this->createForm(ContractSignType::class, null, [
                'sign_platform' => false,
                'sign_merchant' => true,
                'default_merchant_signer' => $this->getShopUser()->getFullName(),
                'default_merchant_title' => $shop->getMerchant()?->getRepresentativeTitle() ?: 'Gérant',
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $contracts->signMerchant(
                    $contract,
                    (string) $form->get('merchantSignedBy')->getData(),
                    (string) $form->get('merchantSignedTitle')->getData(),
                    $this->getShopUser()
                );
                $this->addFlash('success', 'Votre signature a été enregistrée sur le contrat.');

                return $this->redirectToRoute('app_shop_contract', ['id' => $shop->getId()]);
            }
        }

        return $this->render('shop/contract.html.twig', [
            'shop' => $shop,
            'contract' => $contract,
            'form' => $form,
            'platform' => $contracts->getPlatform(),
        ]);
    }

    #[Route('/{id}/contrat.pdf', name: 'app_shop_contract_pdf')]
    #[IsGranted('ROLE_MERCHANT')]
    public function contractPdf(Shop $shop, ContractService $contracts): Response
    {
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);
        $contract = $shop->getContract();
        if (!$contract) {
            throw $this->createNotFoundException();
        }

        $pdf = $contracts->generatePdf($contract);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$contract->getNumber().'.pdf"',
        ]);
    }

    #[Route('/{id}/contrat/imprimer', name: 'app_shop_contract_print')]
    #[IsGranted('ROLE_MERCHANT')]
    public function contractPrint(Shop $shop, ContractService $contracts): Response
    {
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);
        $contract = $shop->getContract();
        if (!$contract) {
            throw $this->createNotFoundException();
        }

        return $this->render('contract/print_merchant.html.twig', [
            'contract' => $contract,
            'shop' => $shop,
            'merchant' => $contract->getMerchant(),
            'user' => $contract->getMerchant()?->getUser(),
            'platform' => $contracts->getPlatform(),
        ]);
    }
}
