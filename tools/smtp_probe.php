#!/usr/bin/env php
<?php

/**
 * Sonde SMTP brute (sans Symfony) pour Hostinger.
 *
 *   php tools/smtp_probe.php "contact@ndamstore.sowcoder.com" "MOT_DE_PASSE"
 */

declare(strict_types=1);

if ($argc < 3) {
    fwrite(STDERR, "Usage: php tools/smtp_probe.php EMAIL MOT_DE_PASSE\n");
    exit(1);
}

$user = trim($argv[1]);
$pass = (string) $argv[2];

echo 'PHP '.PHP_VERSION."\n";
echo 'OpenSSL : '.(extension_loaded('openssl') ? 'oui' : 'NON')."\n";
echo "User    : {$user}\n\n";

/**
 * @return array{ok: bool, log: string}
 */
function probe(string $host, int $port, string $mode, string $user, string $pass): array
{
    $log = [];
    $log[] = "=== {$host}:{$port} ({$mode}) ===";

    $remote = $mode === 'ssl' ? 'ssl://'.$host.':'.$port : $host.':'.$port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
        ],
    ]);

    $errno = 0;
    $errstr = '';
    $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if ($fp === false) {
        $log[] = "CONNECT FAIL: [{$errno}] {$errstr}";

        return ['ok' => false, 'log' => implode("\n", $log)];
    }
    stream_set_timeout($fp, 20);
    $log[] = 'CONNECT OK';

    $read = static function ($fp) use (&$log): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            $log[] = '<< '.rtrim($line);
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $data;
    };
    $write = static function ($fp, string $cmd) use (&$log): void {
        $log[] = '>> '.$cmd;
        fwrite($fp, $cmd."\r\n");
    };

    $banner = $read($fp);
    if (!str_starts_with($banner, '220')) {
        fclose($fp);

        return ['ok' => false, 'log' => implode("\n", $log)];
    }

    $write($fp, 'EHLO ndamstore.sowcoder.com');
    $ehlo = $read($fp);

    if ($mode === 'tls') {
        if (!str_contains(strtoupper($ehlo), 'STARTTLS')) {
            $log[] = 'STARTTLS non annoncé';
            fclose($fp);

            return ['ok' => false, 'log' => implode("\n", $log)];
        }
        $write($fp, 'STARTTLS');
        $tls = $read($fp);
        if (!str_starts_with($tls, '220')) {
            fclose($fp);

            return ['ok' => false, 'log' => implode("\n", $log)];
        }
        $crypto = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
        if ($crypto !== true) {
            $log[] = 'TLS handshake FAIL';
            fclose($fp);

            return ['ok' => false, 'log' => implode("\n", $log)];
        }
        $log[] = 'TLS OK';
        $write($fp, 'EHLO ndamstore.sowcoder.com');
        $read($fp);
    }

    $write($fp, 'AUTH LOGIN');
    $read($fp);
    $write($fp, base64_encode($user));
    $read($fp);
    $write($fp, base64_encode($pass));
    $auth = $read($fp);
    $ok = str_starts_with($auth, '235');

    $write($fp, 'QUIT');
    fclose($fp);
    $log[] = $ok ? 'AUTH OK' : 'AUTH FAIL (mauvais mot de passe ou compte)';

    return ['ok' => $ok, 'log' => implode("\n", $log)];
}

$targets = [
    ['smtp.hostinger.com', 465, 'ssl'],
    ['smtp.hostinger.com', 587, 'tls'],
];

$anyOk = false;
foreach ($targets as [$host, $port, $mode]) {
    $result = probe($host, $port, $mode, $user, $pass);
    echo $result['log']."\n\n";
    if ($result['ok']) {
        $anyOk = true;
    }
}

// Fallback PHP mail()
echo "=== native PHP mail() ===\n";
$subject = 'Test mail() NdamStore';
$body = "Test sendmail/native depuis Hostinger\n".date('c');
$headers = 'From: '.$user."\r\n".'Content-Type: text/plain; charset=UTF-8';
$mailOk = @mail($user, $subject, $body, $headers);
echo $mailOk ? "mail() OK (envoyé vers {$user})\n" : "mail() FAIL\n";

if ($anyOk) {
    echo "\nCONCLUSION : SMTP auth OK — utilisez ce port dans configure_hostinger_mail.php\n";
    exit(0);
}

if ($mailOk) {
    echo "\nCONCLUSION : SMTP KO mais mail() OK — basculez MAILER_DSN=native://default\n";
    exit(2);
}

echo "\nCONCLUSION : SMTP et mail() en échec. Vérifiez la boîte email / mot de passe dans hPanel.\n";
exit(1);
