<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileType;
use App\Repository\NotificationRepository;
use App\Service\ActivityLogger;
use App\Service\AppMailer;
use App\Service\ShopContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        ActivityLogger $logger,
        ShopContext $shopContext,
        AppMailer $mailer,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plain = $form->get('plainPassword')->getData();
            $passwordChanged = false;
            if ($plain) {
                $user->setPassword($hasher->hashPassword($user, $plain));
                $passwordChanged = true;
            }
            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $shop = $shopContext->getCurrentShop($user);
            $logger->log(
                $passwordChanged ? 'profile.password' : 'profile.update',
                $passwordChanged ? 'Mot de passe modifié' : 'Profil mis à jour',
                $user,
                $shop
            );
            if ($passwordChanged) {
                $mailer->sendPasswordChanged($user);
            }
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('profile/index.html.twig', ['form' => $form]);
    }

    #[Route('/notifications', name: 'app_notifications')]
    public function notifications(NotificationRepository $repo): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('notification/index.html.twig', [
            'notifications' => $repo->findBy(['user' => $user], ['createdAt' => 'DESC'], 50),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'app_notification_read', methods: ['POST'])]
    public function markRead(int $id, Request $request, NotificationRepository $repo, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $notification = $repo->find($id);
        if ($notification && $notification->getUser()?->getId() === $user->getId()
            && $this->isCsrfTokenValid('read'.$id, $request->request->get('_token'))) {
            $notification->setIsRead(true);
            $em->flush();
        }

        return $this->redirectToRoute('app_notifications');
    }
}
