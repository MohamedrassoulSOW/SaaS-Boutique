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
            if ($e->getPrevious()) {
                $io->writeln('Cause  : '.$e->getPrevious()->getMessage());
            }
            $io->section('Pistes Hostinger');
            $io->listing([
                'Créer la boîte contact@ndamstore.sowcoder.com dans hPanel → Emails',
                'MAILER_DSN=smtps://contact%40ndamstore.sowcoder.com:MOT_DE_PASSE@smtp.hostinger.com:465',
                'Si caractères spéciaux dans le mot de passe : les URL-encoder (@→%40 #→%23 :→%3A /→%2F espace→%20)',
                'Alternative port 587 : smtp://contact%40ndamstore.sowcoder.com:MDP@smtp.hostinger.com:587',
                'MAIL_FROM doit utiliser la même adresse que le compte SMTP',
                'Puis : php bin/console cache:clear --env=prod --no-debug',
            ]);

            return Command::FAILURE;
        }
    }
}
