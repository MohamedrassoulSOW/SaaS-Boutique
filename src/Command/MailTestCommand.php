<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:mail:test',
    description: 'Envoie un email de test pour diagnostiquer SMTP (Hostinger)',
)]
class MailTestCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(MAIL_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('to', InputArgument::REQUIRED, 'Adresse destinataire de test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $to = (string) $input->getArgument('to');

        $dsnSafe = preg_replace('#:(//[^:]+:)[^@]+@#', '$1***@', $this->mailerDsn) ?? $this->mailerDsn;
        $io->writeln('MAILER_DSN : '.$dsnSafe);
        $io->writeln('MAIL_FROM  : '.$this->mailFrom);
        $io->writeln('OpenSSL    : '.(extension_loaded('openssl') ? 'oui' : 'NON — requis pour SMTP SSL'));
        $io->writeln('PHP        : '.PHP_VERSION);

        if (str_contains($this->mailerDsn, 'null://') || str_contains($this->mailerDsn, 'VOTRE_MOT_DE_PASSE')) {
            $io->error('MAILER_DSN n’est pas configuré (null:// ou placeholder).');
            $io->writeln('Utilisez : php tools/configure_hostinger_mail.php "contact@…" "motdepasse" "test@…"');

            return Command::FAILURE;
        }

        if (!extension_loaded('openssl')) {
            $io->error('Extension OpenSSL absente — impossible d’utiliser SMTP sécurisé.');

            return Command::FAILURE;
        }

        $from = str_contains($this->mailFrom, '<')
            ? Address::create($this->mailFrom)
            : new Address($this->mailFrom, 'NdamStore');

        $email = (new Email())
            ->from($from)
            ->to($to)
            ->subject('Test SMTP NdamStore')
            ->text("Email de test NdamStore.\nSi vous recevez ce message, la config SMTP est OK.\n");

        try {
            $this->mailer->send($email);
            $io->success('Email envoyé à '.$to.' — vérifiez la boîte (et les spams).');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Échec SMTP : '.$e->getMessage());
            $io->writeln('Classe : '.$e::class);
            $prev = $e;
            $depth = 0;
            while ($prev->getPrevious() && $depth < 5) {
                $prev = $prev->getPrevious();
                ++$depth;
                $io->writeln('Cause '.$depth.' : '.$prev->getMessage());
            }
            @file_put_contents(
                dirname(__DIR__, 2).'/var/log/mail-error.log',
                date('c').' '.$e->getMessage()."\n".$e->getTraceAsString()."\n\n",
                FILE_APPEND
            );
            $io->section('Correction rapide');
            $io->writeln('php tools/configure_hostinger_mail.php "contact@ndamstore.sowcoder.com" "VOTRE_MDP" "'.$to.'"');
            $io->writeln('Si échec : ajoutez 587 à la fin pour tenter STARTTLS');
            $io->writeln('php tools/configure_hostinger_mail.php "contact@ndamstore.sowcoder.com" "VOTRE_MDP" "'.$to.'" 587');

            return Command::FAILURE;
        }
    }
}
