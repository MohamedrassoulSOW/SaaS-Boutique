<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$ok = 0;
$fail = 0;
$warn = 0;

$check = static function (string $label, bool $pass, string $detail = '', bool $warning = false) use (&$ok, &$fail, &$warn): void {
    if ($pass) {
        ++$ok;
        echo "[OK]   {$label}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
        return;
    }
    if ($warning) {
        ++$warn;
        echo "[WARN] {$label}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
        return;
    }
    ++$fail;
    echo "[FAIL] {$label}".($detail !== '' ? " — {$detail}" : '').PHP_EOL;
};

// Assets PWA
foreach (['icon-192.png', 'icon-512.png', 'apple-touch-icon.png', 'maskable-512.png'] as $icon) {
    $check('PWA icon '.$icon, file_exists($root.'/public/icons/'.$icon));
}
$check('manifest.webmanifest', file_exists($root.'/public/manifest.webmanifest'));
$check('sw.js', file_exists($root.'/public/sw.js'));
$check('offline.html', file_exists($root.'/public/offline.html'));

// Emails
$mails = [
    'reset_password.html.twig',
    'reset_password.txt.twig',
    'contact.html.twig',
    'welcome_account.html.twig',
    'password_changed.html.twig',
    'shop_created.html.twig',
    'contract_notice.html.twig',
    'subscription_alert.html.twig',
    'account_status.html.twig',
    '_layout.html.twig',
];
foreach ($mails as $mail) {
    $check('Email template '.$mail, file_exists($root.'/templates/emails/'.$mail));
}

// Core services
$check('AppMailer.php', file_exists($root.'/src/Service/AppMailer.php'));
$check('prod mailer.yaml', file_exists($root.'/config/packages/prod/mailer.yaml'));
$check('.env.prod', file_exists($root.'/.env.prod'));
$check('.env.prod.local.example', file_exists($root.'/.env.prod.local.example'));
$check('.env.prod.local (SMTP réel)', file_exists($root.'/.env.prod.local'), 'à créer sur le serveur', true);

// Env content checks
$env = file_get_contents($root.'/.env') ?: '';
$check('MAIL_FROM contact@ndamstore', str_contains($env, 'contact@ndamstore.sowcoder.com'));
$check('MAILER_DSN local en .env (dev OK)', str_contains($env, '127.0.0.1:1025'), 'dev = Mailpit ; prod via .env.prod.local', true);

$envProd = file_exists($root.'/.env.prod') ? (file_get_contents($root.'/.env.prod') ?: '') : '';
$check('.env.prod APP_DEBUG=0', str_contains($envProd, 'APP_DEBUG=0'));
$check('.env.prod DEFAULT_URI https', str_contains($envProd, 'https://ndamstore.sowcoder.com'));
$check('Security UserChecker', file_exists($root.'/src/Security/UserChecker.php'));
$check('Security headers subscriber', file_exists($root.'/src/EventSubscriber/SecurityHeadersSubscriber.php'));
$check('deploy_prod.php', file_exists($root.'/tools/deploy_prod.php'));
$check('crontab.example', file_exists($root.'/tools/crontab.example'));
$check('monolog.yaml', file_exists($root.'/config/packages/monolog.yaml'));

// Security: APP_SECRET should change in prod
$check('Reminder APP_SECRET unique prod', true, 'générer une nouvelle clé sur le serveur', true);
$check('Reminder NINEA réel dans .env.prod.local', true, 'pas de placeholder 000000000', true);
$check('Reminder cron subscriptions:enforce', true, 'voir tools/crontab.example', true);

echo PHP_EOL."Résumé : {$ok} OK, {$warn} warnings, {$fail} fails".PHP_EOL;
exit($fail > 0 ? 1 : 0);
