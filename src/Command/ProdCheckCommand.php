<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:prod:check',
    description: 'Vérifie que la configuration est prête pour la production',
)]
class ProdCheckCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        #[Autowire('%kernel.environment%')]
        private string $environment,
        #[Autowire('%kernel.debug%')]
        private bool $debug,
        #[Autowire('%env(DEFAULT_URI)%')]
        private string $defaultUri,
        #[Autowire('%env(MAIL_FROM)%')]
        private string $mailFrom,
        #[Autowire('%env(PLATFORM_EMAIL)%')]
        private string $platformEmail,
        #[Autowire('%env(PLATFORM_TAX_ID)%')]
        private string $platformTaxId,
        #[Autowire('%env(MAILER_DSN)%')]
        private string $mailerDsn,
        #[Autowire('%env(APP_SECRET)%')]
        private string $appSecret,
        private Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('NdamStore — contrôle production');

        $fails = 0;
        $warns = 0;

        $check = static function (bool $ok, string $label, string $hint = '', bool $warn = false) use ($io, &$fails, &$warns): void {
            if ($ok) {
                $io->success($label);

                return;
            }
            if ($warn) {
                ++$warns;
                $io->warning($label.($hint !== '' ? ' — '.$hint : ''));

                return;
            }
            ++$fails;
            $io->error($label.($hint !== '' ? ' — '.$hint : ''));
        };

        $check($this->environment === 'prod', 'APP_ENV=prod', 'Lancer avec --env=prod');
        $check(!$this->debug, 'APP_DEBUG=0', 'Désactiver le debug en production');
        $check(
            !str_contains($this->defaultUri, '127.0.0.1') && str_starts_with($this->defaultUri, 'https://'),
            'DEFAULT_URI en HTTPS public',
            $this->defaultUri
        );
        $check(\strlen($this->appSecret) >= 32, 'APP_SECRET suffisamment long (≥ 32)', 'php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"');
        $check(!str_contains($this->appSecret, 'CHANGEZ'), 'APP_SECRET n\'est pas le placeholder');

        $check(
            str_contains($this->mailFrom, 'contact@ndamstore.sowcoder.com')
                || str_contains($this->mailFrom, '@ndamstore.'),
            'MAIL_FROM domaine NdamStore',
            $this->mailFrom
        );
        $check(
            filter_var($this->platformEmail, FILTER_VALIDATE_EMAIL) !== false,
            'PLATFORM_EMAIL valide',
            $this->platformEmail
        );
        $check(
            !str_contains($this->platformTaxId, '000000000'),
            'PLATFORM_TAX_ID réel (pas le placeholder)',
            'Mettre le vrai NINEA dans .env.prod.local',
            true
        );

        $isRealMailer = !str_starts_with($this->mailerDsn, 'null://')
            && !str_contains($this->mailerDsn, '127.0.0.1')
            && !str_contains($this->mailerDsn, 'VOTRE_MOT_DE_PASSE');
        $check($isRealMailer, 'MAILER_DSN SMTP réel configuré', 'Créer .env.prod.local depuis .env.prod.local.example');

        $prodLocal = $this->projectDir.'/.env.prod.local';
        $check(is_file($prodLocal), 'Fichier .env.prod.local présent', 'cp .env.prod.local.example .env.prod.local');

        $check(is_file($this->projectDir.'/public/.htaccess'), 'public/.htaccess présent');
        $check(is_file($this->projectDir.'/.htaccess'), '.htaccess racine présent (hébergement mutualisé)');
        $check(is_file($this->projectDir.'/public/manifest.webmanifest'), 'PWA manifest présent');
        $check(is_dir($this->projectDir.'/var/cache/prod') || $this->environment !== 'prod', 'Cache prod', 'php bin/console cache:warmup --env=prod', true);

        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
            $check(true, 'Connexion base de données OK');
        } catch (\Throwable $e) {
            $check(false, 'Connexion base de données', $e->getMessage());
        }

        try {
            $hasSessions = (bool) $this->connection->executeQuery(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'sessions'"
            )->fetchOne();
            $check($hasSessions, 'Table sessions présente', 'php bin/console app:sessions:init --env=prod');
        } catch (\Throwable) {
            $check(false, 'Vérification table sessions', 'php bin/console app:sessions:init --env=prod', true);
        }

        $writable = ['var/cache', 'var/log'];
        foreach ($writable as $dir) {
            $path = $this->projectDir.'/'.$dir;
            $check(is_dir($path) && is_writable($path), $dir.' accessible en écriture', 'chmod/chown sur le serveur', true);
        }

        $io->section('Cron recommandé');
        $io->writeln('0 3 * * * cd '.$this->projectDir.' && php bin/console app:subscriptions:enforce --env=prod --no-debug');

        $io->section('Résumé');
        if ($fails === 0 && $warns === 0) {
            $io->success('Prêt pour la production.');

            return Command::SUCCESS;
        }
        if ($fails === 0) {
            $io->warning(sprintf('%d avertissement(s) — corriger avant ouverture publique.', $warns));

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d problème(s), %d avertissement(s).', $fails, $warns));

        return Command::FAILURE;
    }
}
