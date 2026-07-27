<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class PasswordResetMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private string $mailFrom,
        private string $appName,
    ) {
    }

    public function sendResetLink(User $user, string $plainToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $email = (new TemplatedEmail())
            ->from(Address::create($this->mailFrom))
            ->to((string) $user->getEmail())
            ->subject(sprintf('Réinitialisation de votre mot de passe — %s', $this->appName))
            ->htmlTemplate('emails/reset_password.html.twig')
            ->textTemplate('emails/reset_password.txt.twig')
            ->context([
                'user' => $user,
                'resetUrl' => $resetUrl,
                'appName' => $this->appName,
                'expiresInHours' => 2,
            ]);

        $this->mailer->send($email);
    }
}
