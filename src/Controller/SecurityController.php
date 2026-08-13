<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ResetPasswordRequestType;
use App\Form\ResetPasswordType;
use App\Repository\UserRepository;
use App\Service\ActivityLogger;
use App\Service\PasswordResetMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('Intercepted by firewall.');
    }

    #[Route('/reset-password', name: 'app_reset_password_request')]
    public function requestReset(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $em,
        PasswordResetMailer $passwordResetMailer,
        ActivityLogger $activityLogger,
        #[Autowire(service: 'limiter.password_reset')]
        RateLimiterFactory $passwordResetLimiter,
    ): Response {
        $form = $this->createForm(ResetPasswordRequestType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $clientIp = $request->getClientIp() ?: 'unknown';
            $limiter = $passwordResetLimiter->create($clientIp);
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('danger', 'Trop de demandes. Réessayez dans une heure.');

                return $this->render('security/reset_request.html.twig', ['form' => $form]);
            }

            $email = strtolower(trim((string) $form->get('email')->getData()));
            $user = $users->findOneBy(['email' => $email]);

            // Toujours le même message (évite l'énumération de comptes)
            $flashMessage = 'Si un compte existe pour cet email, un lien de réinitialisation vient d’être envoyé.';

            if ($user && $user->isActive() && !$user->isSuspended()) {
                $plainToken = bin2hex(random_bytes(32));
                $user->setPasswordResetToken(hash('sha256', $plainToken));
                $user->setPasswordResetRequestedAt(new \DateTimeImmutable());
                $em->flush();

                try {
                    $passwordResetMailer->sendResetLink($user, $plainToken);
                    $activityLogger->log('user.reset_request', 'Demande de reset MDP pour '.$user->getEmail(), $user);
                } catch (\Throwable $e) {
                    $activityLogger->log(
                        'user.reset_mail_fail',
                        'Échec envoi reset : '.$e->getMessage(),
                        $user
                    );
                    $this->addFlash(
                        'danger',
                        'Impossible d’envoyer l’email pour le moment. Vérifiez MAILER_DSN SMTP Hostinger (voir app:mail:test).'
                    );

                    return $this->render('security/reset_request.html.twig', ['form' => $form]);
                }
            }

            $this->addFlash('success', $flashMessage);

            return $this->redirectToRoute('app_reset_password_request');
        }

        return $this->render('security/reset_request.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        UserRepository $users,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em,
        ActivityLogger $activityLogger,
    ): Response {
        $user = $users->findOneBy(['passwordResetToken' => hash('sha256', $token)]);
        if (!$user || !$user->getPasswordResetRequestedAt()
            || $user->getPasswordResetRequestedAt() < new \DateTimeImmutable('-2 hours')) {
            $this->addFlash('danger', 'Lien invalide ou expiré. Merci de refaire une demande.');

            return $this->redirectToRoute('app_reset_password_request');
        }

        $form = $this->createForm(ResetPasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($passwordHasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $user->setPasswordResetToken(null);
            $user->setPasswordResetRequestedAt(null);
            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $activityLogger->log('user.reset_done', 'Mot de passe réinitialisé pour '.$user->getEmail(), $user);
            $this->addFlash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset.html.twig', ['form' => $form]);
    }
}