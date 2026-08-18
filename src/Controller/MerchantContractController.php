<?php

namespace App\Controller;

use App\Entity\ShopContract;
use App\Entity\User;
use App\Form\ContractSignType;
use App\Service\ContractService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/contrats')]
#[IsGranted('ROLE_MERCHANT')]
class MerchantContractController extends AbstractController
{
    #[Route('/{id}', name: 'app_contract_show')]
    public function show(
        ShopContract $contract,
        Request $request,
        ContractService $contracts,
    ): Response {
        $this->assertOwns($contract);

        if (!$contract->isSharedWithMerchant() && $contract->isDraft()) {
            throw $this->createAccessDeniedException();
        }

        $form = null;
        if (!$contract->isDraft()
            && $contract->getStatus() !== ShopContract::STATUS_TERMINATED
            && $contract->getMerchantSignedAt() === null) {
            $form = $this->createForm(ContractSignType::class, null, [
                'sign_platform' => false,
                'sign_merchant' => true,
                'default_merchant_signer' => $this->getUser() instanceof User ? $this->getUser()->getFullName() : '',
                'default_merchant_title' => $contract->getMerchant()?->getRepresentativeTitle() ?: 'Gérant',
            ]);
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                /** @var User $user */
                $user = $this->getUser();
                $contracts->signMerchant(
                    $contract,
                    (string) $form->get('merchantSignedBy')->getData(),
                    (string) $form->get('merchantSignedTitle')->getData(),
                    $user
                );
                $this->addFlash('success', 'Votre signature a été enregistrée.');

                return $this->redirectToRoute('app_contract_show', ['id' => $contract->getId()]);
            }
        }

        return $this->render('contract/merchant_show.html.twig', [
            'contract' => $contract,
            'form' => $form,
            'platform' => $contracts->getPlatform(),
        ]);
    }

    #[Route('/{id}/pdf', name: 'app_contract_pdf')]
    public function pdf(ShopContract $contract, ContractService $contracts): Response
    {
        $this->assertOwns($contract);
        if (!$contract->isSharedWithMerchant() && $contract->isDraft()) {
            throw $this->createAccessDeniedException();
        }

        $pdf = $contracts->generatePdf($contract);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => \Symfony\Component\HttpFoundation\HeaderUtils::makeDisposition('inline', $contract->getNumber().'.pdf'),
        ]);
    }

    #[Route('/{id}/imprimer', name: 'app_contract_print')]
    public function print(ShopContract $contract, ContractService $contracts): Response
    {
        $this->assertOwns($contract);
        if (!$contract->isSharedWithMerchant() && $contract->isDraft()) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('contract/print_merchant_standalone.html.twig', [
            'contract' => $contract,
            'shop' => $contract->getShop(),
            'merchant' => $contract->getMerchant(),
            'user' => $contract->getMerchant()?->getUser(),
            'platform' => $contracts->getPlatform(),
        ]);
    }

    private function assertOwns(ShopContract $contract): void
    {
        /** @var User $user */
        $user = $this->getUser();
        if ($user->getMerchant()?->getId() !== $contract->getMerchant()?->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
