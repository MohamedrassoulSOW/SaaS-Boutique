<?php

namespace App\Controller;

use App\Entity\ShopMember;
use App\Form\ShopMemberType;
use App\Repository\ShopMemberRepository;
use App\Security\Voter\ShopVoter;
use App\Service\ShopContext;
use App\Service\ShopMemberService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vendeurs')]
#[IsGranted('ROLE_MERCHANT')]
class ShopMemberController extends ShopAwareController
{
    #[Route('', name: 'app_staff_index')]
    public function index(ShopContext $shopContext, ShopMemberRepository $members): Response
    {
        $shop = $this->requireShop($shopContext);
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);

        return $this->render('staff/index.html.twig', [
            'shop' => $shop,
            'members' => $members->findByShop($shop),
        ]);
    }

    #[Route('/new', name: 'app_staff_new')]
    public function new(
        Request $request,
        ShopContext $shopContext,
        ShopMemberService $service,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);

        $member = new ShopMember();
        $member->setShop($shop);
        $member->setRole(ShopMember::ROLE_CASHIER);
        $member->setIsActive(true);

        $form = $this->createForm(ShopMemberType::class, $member, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->create($shop, $this->getShopUser(), [
                    'firstName' => (string) $form->get('firstName')->getData(),
                    'lastName' => (string) $form->get('lastName')->getData(),
                    'email' => (string) $form->get('email')->getData(),
                    'phone' => $form->get('phone')->getData(),
                    'plainPassword' => $form->get('plainPassword')->getData(),
                ], (string) $form->get('role')->getData());

                $this->addFlash('success', 'Accès vendeur créé. Un email d\'accès a été envoyé.');

                return $this->redirectToRoute('app_staff_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('staff/form.html.twig', [
            'form' => $form,
            'title' => 'Nouvel accès vendeur',
            'member' => null,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_staff_edit')]
    public function edit(
        ShopMember $member,
        Request $request,
        ShopContext $shopContext,
        ShopMemberService $service,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);
        $this->assertShopData($shopContext, $member->getShop());

        if ($member->getUser()?->isMerchant() || $member->getUser()?->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->createForm(ShopMemberType::class, $member, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $service->update(
                    $member,
                    $this->getShopUser(),
                    [
                        'firstName' => (string) $form->get('firstName')->getData(),
                        'lastName' => (string) $form->get('lastName')->getData(),
                        'email' => (string) $form->get('email')->getData(),
                        'phone' => $form->get('phone')->getData(),
                        'plainPassword' => $form->get('plainPassword')->getData(),
                    ],
                    (string) $form->get('role')->getData(),
                    (bool) $form->get('isActive')->getData(),
                );

                $this->addFlash('success', 'Accès vendeur mis à jour.');

                return $this->redirectToRoute('app_staff_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->render('staff/form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l\'accès vendeur',
            'member' => $member,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_staff_delete', methods: ['POST'])]
    public function delete(
        ShopMember $member,
        Request $request,
        ShopContext $shopContext,
        ShopMemberService $service,
    ): Response {
        $shop = $this->requireShop($shopContext);
        $this->denyAccessUnlessGranted(ShopVoter::MANAGE, $shop);
        $this->assertShopData($shopContext, $member->getShop());

        if ($member->getUser()?->isMerchant() || $member->getUser()?->isAdmin()) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_staff'.$member->getId(), $request->request->get('_token'))) {
            $service->delete($member, $this->getShopUser());
            $this->addFlash('success', 'Accès vendeur supprimé.');
        }

        return $this->redirectToRoute('app_staff_index');
    }
}
