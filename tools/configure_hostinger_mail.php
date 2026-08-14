#!/usr/bin/env php
<?php

/**
 * Configure MAILER_DSN Hostinger et envoie un email de test.
 *
 * SMTP (recommandé) :
 *   php tools/configure_hostinger_mail.php "contact@ndamstore.sowcoder.com" "MotDePasse" "destinataire@gmail.com"
 *   php tools/configure_hostinger_mail.php "contact@…" "MotDePasse" "test@…" 587
 *
 * Fallback PHP mail() (si SMTP bloqué sur mutualisé) :
 *   php tools/configure_hostinger_mail.php "contact@ndamstore.sowcoder.com" "-" "destinataire@gmail.com" native
 *
 * Diagnostic brut avant config :
 *   php tools/smtp_probe.php "contact@…" "MotDePasse"
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

if ($argc < 4) {
    fwrite(STDERR, "Usage:\n");
    fwrite(STDERR, "  php tools/configure_hostinger_mail.php EMAIL_FROM MOT_DE_PASSE EMAIL_TEST [465|587|native]\n");
    fwrite(STDERR, "  php tools/smtp_probe.php EMAIL_FROM MOT_DE_PASSE\n");
    exit(1);
}

$smtpUser = trim($argv[1]);
$smtpPass = (string) $argv[2];
$testTo = trim($argv[3]);
$mode = isset($argv[4]) ? strtolower(trim((string) $argv[4])) : '465';

if (!filter_var($smtpUser, FILTER_VALIDATE_EMAIL) || !filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Emails invalides.\n");
    exit(1);
}

if ($mode === 'native' || $mode === 'sendmail') {
    // Sur Hostinger mutualisé, mail()/sendmail passe souvent quand SMTP sortant est filtré.
    $dsn = 'native://default';
    $label = 'native (PHP mail)';
} elseif ($mode === '465' || $mode === 'smtps') {
    if ($smtpPass === '' || $smtpPass === '-') {
        fwrite(STDERR, "Mot de passe SMTP requis pour le port 465.\n");
        exit(1);
    }
    $dsn = sprintf(
        'smtps://%s:%s@smtp.hostinger.com:465',
        rawurlencode($smtpUser),
        rawurlencode($smtpPass)
    );
    $label = 'SMTP SSL 465';
} elseif ($mode === '587' || $mode === 'tls') {
    if ($smtpPass === '' || $smtpPass === '-') {
        fwrite(STDERR, "Mot de passe SMTP requis pour le port 587.\n");
        exit(1);
    }
    $dsn = sprintf(
        'smtp://%s:%s@smtp.hostinger.com:587',
        rawurlencode($smtpUser),
        rawurlencode($smtpPass)
    );
    $label = 'SMTP STARTTLS 587';
} else {
    fwrite(STDERR, "Mode invalide (465, 587 ou native).\n");
    exit(1);
}

$mailFrom = sprintf('NdamStore <%s>', $smtpUser);
$envelope = $smtpUser;

$local = $root.'/.env.prod.local';
if (!is_file($local)) {
    if (is_file($root.'/.env.prod.local.example')) {
        copy($root.'/.env.prod.local.example', $local);
        echo "Créé .env.prod.local depuis l'exemple.\n";
    } else {
        file_put_contents($local, "APP_ENV=prod\nAPP_DEBUG=0\n");
        echo "Créé .env.prod.local vide.\n";
    }
}

$content = file_get_contents($local);
if ($content === false) {
    fwrite(STDERR, "Impossible de lire .env.prod.local\n");
    exit(1);
}

$set = static function (string $content, string $key, string $value): string {
    $line = $key.'='.$value;
    if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
        return preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content, 1) ?? $content;
    }

    return rtrim($content)."\n".$line."\n";
};

$dsnQuoted = '"'.str_replace('"', '\\"', $dsn).'"';
$fromQuoted = '"'.str_replace('"', '\\"', $mailFrom).'"';

$content = $set($content, 'MAILER_DSN', $dsnQuoted);
$content = $set($content, 'MAIL_FROM', $fromQuoted);
$content = $set($content, 'MAIL_ENVELOPE_SENDER', $envelope);
$content = $set($content, 'APP_ENV', 'prod');
$content = $set($content, 'APP_DEBUG', '0');

if (file_put_contents($local, $content) === false) {
    fwrite(STDERR, "Impossible d'écrire .env.prod.local\n");
    exit(1);
}

echo "OK — .env.prod.local mis à jour ({$label}).\n";
echo 'DSN (masqué) : '.preg_replace('#:(//[^:]+:)[^@]+@#', '$1***@', $dsn)."\n";

passthru(escapeshellarg(PHP_BINARY).' bin/console cache:clear --env=prod --no-debug', $cacheCode);
if ($cacheCode !== 0) {
    fwrite(STDERR, "Avertissement : cache:clear code {$cacheCode}\n");
}

echo "\nEnvoi du test vers {$testTo}…\n";
passthru(
    escapeshellarg(PHP_BINARY).' bin/console app:mail:test '.escapeshellarg($testTo).' --env=prod --no-debug',
    $mailCode
);

exit($mailCode === 0 ? 0 : 1);
