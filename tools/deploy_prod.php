#!/usr/bin/env php
<?php

/**
 * Déploiement production NdamStore
 *
 *   php tools/deploy_prod.php
 *   SKIP_COMPOSER=1 php tools/deploy_prod.php   # si vendor déjà OK
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function run(string $cmd): int
{
    echo "\n> {$cmd}\n";
    flush();
    $code = 0;
    passthru($cmd, $code);
    $code = (int) $code;
    echo "→ exit {$code}\n";
    flush();
    if ($code !== 0) {
        fwrite(STDERR, "Échec (code {$code}) : {$cmd}\n");
    }

    return $code;
}

echo "=== NdamStore deploy prod ===\n";
echo 'PHP CLI : '.PHP_VERSION.' ('.PHP_BINARY.")\n";
flush();

if (PHP_VERSION_ID < 80400) {
    fwrite(STDERR, "ERREUR : PHP ≥ 8.4 requis (actuel ".PHP_VERSION.").\n");
    exit(1);
}

if (!is_file($root.'/.env.prod.local')) {
    fwrite(STDERR, "ERREUR : créez .env.prod.local\n");
    exit(1);
}

$php = escapeshellarg(PHP_BINARY);
$skipComposer = getenv('SKIP_COMPOSER') === '1' || getenv('SKIP_COMPOSER') === 'true';

if (!$skipComposer) {
    $composerCmd = null;
    if (is_file($root.'/composer.phar')) {
        $composerCmd = $php.' '.escapeshellarg($root.'/composer.phar');
    } else {
        // Forcer composer via le même binaire PHP (évite un autre php CLI)
        $which = trim((string) shell_exec('command -v composer 2>/dev/null || which composer 2>/dev/null'));
        if ($which !== '') {
            $composerCmd = $php.' '.escapeshellarg($which);
        }
    }

    if ($composerCmd === null) {
        fwrite(STDERR, "ERREUR : composer introuvable. Lancez manuellement :\n");
        fwrite(STDERR, "  composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts\n");
        exit(1);
    }

    $code = run($composerCmd.' install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts');
    if ($code !== 0) {
        // Sur certains hébergeurs composer renvoie un code bizarre alors que vendor est OK
        if (!is_dir($root.'/vendor/doctrine/orm')) {
            exit(1);
        }
        echo "AVERTISSEMENT : code composer={$code} mais vendor/doctrine/orm présent — on continue.\n";
    }
} else {
    echo "SKIP_COMPOSER=1 — étape composer ignorée.\n";
}

if (!is_dir($root.'/vendor/doctrine/orm')) {
    fwrite(STDERR, "ERREUR : vendor/doctrine/orm absent. Lancez composer install avant.\n");
    exit(1);
}

$steps = [
    $php.' bin/console doctrine:migrations:migrate --no-interaction --env=prod --no-debug',
    $php.' bin/console app:sessions:init --env=prod --no-debug',
    $php.' bin/console cache:clear --env=prod --no-debug',
    $php.' bin/console cache:warmup --env=prod --no-debug',
    $php.' bin/console assets:install public --env=prod --no-debug',
    $php.' bin/console app:prod:check --env=prod --no-debug',
];

foreach ($steps as $step) {
    if (run($step) !== 0) {
        exit(1);
    }
}

echo "\n=== Déploiement terminé ===\n";
echo "Document root → public/\n";
echo "Cron : 0 3 * * * cd {$root} && php bin/console app:subscriptions:enforce --env=prod --no-debug\n";
