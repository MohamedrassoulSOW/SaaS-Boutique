#!/usr/bin/env php
<?php

/**
 * Répare l'envoi d'emails sur Hostinger (sans dépendre de app:mail:test).
 *
 * Usage SSH (racine public_html) :
 *   php tools/fix_mail_prod.php "contact@ndamstore.sowcoder.com" "MOT_DE_PASSE_BOITE" "destinataire@gmail.com"
 *
 * Sans mot de passe (native uniquement) :
 *   php tools/fix_mail_prod.php "contact@ndamstore.sowcoder.com" "-" "destinataire@gmail.com" native
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

if ($argc < 4) {
    fwrite(STDERR, "Usage: php tools/fix_mail_prod.php FROM PASS TO [465|587|native|failover]\n");
    exit(1);
}

$fromEmail = trim($argv[1]);
$pass = (string) $argv[2];
$to = trim($argv[3]);
$mode = isset($argv[4]) ? strtolower(trim((string) $argv[4])) : 'failover';

if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Emails invalides.\n");
    exit(1);
}

$userEnc = rawurlencode($fromEmail);
$passEnc = rawurlencode($pass);

$dsn = match ($mode) {
    'native', 'sendmail' => 'native://default',
    '465', 'smtps' => sprintf('smtps://%s:%s@smtp.hostinger.com:465', $userEnc, $passEnc),
    '587', 'tls' => sprintf('smtp://%s:%s@smtp.hostinger.com:587', $userEnc, $passEnc),
    default => sprintf(
        'failover(smtps://%s:%s@smtp.hostinger.com:465 native://default)',
        $userEnc,
        $passEnc
    ),
};

if (\in_array($mode, ['failover', '465', 'smtps', '587', 'tls', ''], true) && ($pass === '' || $pass === '-')) {
    if ($mode === 'failover' || $mode === '') {
        $dsn = 'native://default';
        $mode = 'native';
        echo "Pas de mot de passe → bascule native://default\n";
    } else {
        fwrite(STDERR, "Mot de passe SMTP requis pour ce mode.\n");
        exit(1);
    }
}

$mailFrom = sprintf('NdamStore <%s>', $fromEmail);
$local = $root.'/.env.prod.local';

if (!is_file($local)) {
    $example = $root.'/.env.prod.local.example';
    if (is_file($example)) {
        copy($example, $local);
        echo "Créé .env.prod.local depuis l'exemple.\n";
    } else {
        file_put_contents($local, "APP_ENV=prod\nAPP_DEBUG=0\n");
    }
}

$content = (string) file_get_contents($local);
$set = static function (string $content, string $key, string $value): string {
    $line = $key.'='.$value;
    if (preg_match('/^'.preg_quote($key, '/').'=.*/m', $content)) {
        return (string) preg_replace('/^'.preg_quote($key, '/').'=.*/m', $line, $content, 1);
    }

    return rtrim($content)."\n".$line."\n";
};

$dsnQuoted = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $dsn).'"';
$fromQuoted = '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $mailFrom).'"';

$content = $set($content, 'APP_ENV', 'prod');
$content = $set($content, 'APP_DEBUG', '0');
$content = $set($content, 'MAILER_DSN', $dsnQuoted);
$content = $set($content, 'MAIL_FROM', $fromQuoted);
$content = $set($content, 'MAIL_ENVELOPE_SENDER', $fromEmail);

if (file_put_contents($local, $content) === false) {
    fwrite(STDERR, "Impossible d'écrire .env.prod.local\n");
    exit(1);
}

$dsnSafe = preg_replace('#:(//[^:\\s]+:)[^@\\s]+@#', '$1***@', $dsn) ?? $dsn;
echo "OK — MAILER_DSN mis à jour ({$mode})\n";
echo "DSN : {$dsnSafe}\n\n";

// 1) Test PHP mail() brut
echo "=== Test 1 : PHP mail() ===\n";
$headers = 'From: '.$fromEmail."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$mailOk = @mail($to, 'Test mail() NdamStore', "Test PHP mail() ".date('c'), $headers);
echo $mailOk ? "mail() = true (Hostinger a accepté le message)\n" : "mail() = false (bloqué)\n";
echo "\n";

// 2) Clear cache + Symfony mailer
$php = PHP_BINARY;
passthru(escapeshellarg($php).' bin/console cache:clear --env=prod --no-debug', $cacheCode);
echo "cache:clear exit={$cacheCode}\n\n";

echo "=== Test 2 : Symfony Mailer ===\n";
passthru(
    escapeshellarg($php).' bin/console app:mail:test '.escapeshellarg($to).' --env=prod --no-debug',
    $symfonyCode
);

if ($symfonyCode !== 0) {
    // Fallback si la commande n'existe pas encore sur le serveur
    echo "\nCommande app:mail:test absente ou en échec — envoi via Kernel…\n";
    try {
        require $root.'/vendor/autoload.php';
        $kernel = new \App\Kernel('prod', false);
        $kernel->boot();
        $container = $kernel->getContainer();
        /** @var \Symfony\Component\Mailer\MailerInterface $mailer */
        $mailer = $container->get('mailer.mailer');
        $email = (new \Symfony\Component\Mime\Email())
            ->from(new \Symfony\Component\Mime\Address($fromEmail, 'NdamStore'))
            ->to($to)
            ->subject('Test Kernel NdamStore')
            ->text('Test via Kernel '.date('c'));
        $mailer->send($email);
        echo "Kernel mailer : OK\n";
        $symfonyCode = 0;
    } catch (\Throwable $e) {
        echo 'Kernel mailer : FAIL — '.$e->getMessage()."\n";
        $prev = $e->getPrevious();
        if ($prev) {
            echo 'Cause : '.$prev->getMessage()."\n";
        }
        $symfonyCode = 1;
    }
}

echo "\n=== Résumé ===\n";
echo 'PHP mail() : '.($mailOk ? 'OK' : 'FAIL')."\n";
echo 'Symfony    : '.($symfonyCode === 0 ? 'OK' : 'FAIL')."\n";
echo "Vérifiez la boîte {$to} (et les spams).\n";

if ($symfonyCode === 0 || $mailOk) {
    exit(0);
}

fwrite(STDERR, "Aucun transport n'a fonctionné. Vérifiez la boîte email dans hPanel (Emails).\n");
exit(1);
