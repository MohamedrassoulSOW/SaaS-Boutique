<?php

namespace App\Service;

use App\Entity\Shop;
use App\Entity\ShopContract;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envois transactionnels NdamStore (expéditeur = MAIL_FROM / contact plateforme).
 */
class AppMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        private string $mailFrom,
        private string $appName,
        /** @var array<string, string> */
        private array $platform,
    ) {
    }

    public function sendPasswordReset(User $user, string $plainToken): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'app_reset_password',
            ['token' => $plainToken],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $this->sendToUser(
            $user,
            sprintf('Réinitialisation de votre mot de passe — %s', $this->appName),
            'emails/reset_password.html.twig',
            'emails/reset_password.txt.twig',
            [
                'user' => $user,
                'resetUrl' => $resetUrl,
                'appName' => $this->appName,
                'expiresInHours' => 2,
            ],
            strict: true
        );
    }

    public function sendContactMessage(string $senderName, string $senderEmail, string $subject, string $body): void
    {
        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->to(new Address($this->platform['email'], $this->platform['legal_name']))
            ->replyTo(new Address($senderEmail, $senderName))
            ->subject('[Contact] '.$subject)
            ->htmlTemplate('emails/contact.html.twig')
            ->context([
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'subject' => $subject,
                'body' => $body,
                'platform' => $this->platform,
                'appName' => $this->appName,
            ]);

        $this->dispatch($email, strict: false);
    }


    public function sendWelcomeMerchant(User $user, string $plainPassword): void
    {
        $this->sendToUser(
            $user,
            sprintf('Bienvenue sur %s — votre compte entrepreneur', $this->appName),
            'emails/welcome_account.html.twig',
            null,
            [
                'user' => $user,
                'plainPassword' => $plainPassword,
                'roleLabel' => 'entrepreneur',
                'loginUrl' => $this->loginUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendWelcomeStaff(User $user, string $plainPassword, Shop $shop): void
    {
        $this->sendToUser(
            $user,
            sprintf('Votre accès %s — %s', $this->appName, $shop->getName()),
            'emails/welcome_account.html.twig',
            null,
            [
                'user' => $user,
                'plainPassword' => $plainPassword,
                'roleLabel' => 'collaborateur',
                'shopName' => $shop->getName(),
                'loginUrl' => $this->loginUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendPasswordChanged(User $user, ?string $plainPassword = null): void
    {
        $this->sendToUser(
            $user,
            sprintf('Mot de passe mis à jour — %s', $this->appName),
            'emails/password_changed.html.twig',
            null,
            [
                'user' => $user,
                'plainPassword' => $plainPassword,
                'loginUrl' => $this->loginUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendShopCreated(User $user, Shop $shop, ShopContract $contract): void
    {
        $this->sendToUser(
            $user,
            sprintf('Entreprise « %s » créée — %s', $shop->getName(), $this->appName),
            'emails/shop_created.html.twig',
            null,
            [
                'user' => $user,
                'shop' => $shop,
                'contract' => $contract,
                'dashboardUrl' => $this->dashboardUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendContractNotice(User $user, ShopContract $contract, string $kind): void
    {
        $titles = [
            'discussion' => 'Nouveau contrat en discussion',
            'shared' => 'Contrat disponible sur votre espace',
            'signature' => 'Contrat prêt à signer',
        ];
        $title = $titles[$kind] ?? 'Mise à jour de contrat';

        $this->sendToUser(
            $user,
            sprintf('%s — %s', $title, $this->appName),
            'emails/contract_notice.html.twig',
            null,
            [
                'user' => $user,
                'contract' => $contract,
                'kind' => $kind,
                'title' => $title,
                'dashboardUrl' => $this->dashboardUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendSubscriptionAlert(User $user, string $title, string $message, bool $terminal = false): void
    {
        $this->sendToUser(
            $user,
            sprintf('%s — %s', $title, $this->appName),
            'emails/subscription_alert.html.twig',
            null,
            [
                'user' => $user,
                'title' => $title,
                'message' => $message,
                'terminal' => $terminal,
                'loginUrl' => $this->loginUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    public function sendAccountStatus(User $user, bool $suspended): void
    {
        $this->sendToUser(
            $user,
            sprintf(
                '%s — %s',
                $suspended ? 'Compte suspendu' : 'Compte réactivé',
                $this->appName
            ),
            'emails/account_status.html.twig',
            null,
            [
                'user' => $user,
                'suspended' => $suspended,
                'loginUrl' => $this->loginUrl(),
                'appName' => $this->appName,
                'platform' => $this->platform,
            ]
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function sendToUser(
        User $user,
        string $subject,
        string $htmlTemplate,
        ?string $textTemplate,
        array $context,
        bool $strict = false,
    ): void {
        $to = (string) $user->getEmail();
        if ($to === '') {
            return;
        }

        $email = (new TemplatedEmail())
            ->from($this->fromAddress())
            ->to(new Address($to, $user->getFullName() ?: $to))
            ->replyTo($this->fromAddress())
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->context($context);

        if ($textTemplate) {
            $email->textTemplate($textTemplate);
        }

        $this->dispatch($email, $strict);
    }

    private function dispatch(TemplatedEmail $email, bool $strict = false): void
    {
        try {
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Échec envoi email NdamStore : '.$e->getMessage(), [
                'subject' => $email->getSubject(),
            ]);
            if ($strict) {
                throw $e;
            }
        }
    }

    private function fromAddress(): Address
    {
        return \str_contains($this->mailFrom, '<')
            ? Address::create($this->mailFrom)
            : new Address($this->mailFrom, $this->appName);
    }

    private function loginUrl(): string
    {
        return $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function dashboardUrl(): string
    {
        return $this->urlGenerator->generate('app_dashboard', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
