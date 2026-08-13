#!/usr/bin/env php
<?php

/**
 * Déploiement production NdamStore
 * Usage (sur le serveur, à la racine du projet) :
 *   php tools/deploy_prod.php
 *   ou : composer prod:deploy
 *
 * Prérequis :
 *   - PHP ≥ 8.4 (CLI Hostinger : souvent /opt/alt/php84/usr/bin/php)
 *   - Document root → public/
 *   - Fichier .env.prod.local renseigné
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function run(string $cmd): int
{
    echo "\n> {$cmd}\n";
    passthru($cmd, $code);
    $code = (int) $code;
    if ($code !== 0) {
        fwrite(STDERR, "Échec (code {$code}) : {$cmd}\n");
    }

    return $code;
}

echo "=== NdamStore deploy prod ===\n";
echo 'PHP CLI : '.PHP_VERSION.' ('.PHP_BINARY.")\n";

if (PHP_VERSION_ID < 80400) {
    fwrite(STDERR, "ERREUR : PHP ≥ 8.4 requis (actuel ".PHP_VERSION.").\n");
    fwrite(STDERR, "Hostinger → hPanel → PHP Configuration → 8.4\n");
    fwrite(STDERR, "Ou en SSH : /opt/alt/php84/usr/bin/php tools/deploy_prod.php\n");
    exit(1);
}

if (!is_file($root.'/.env.prod.local')) {
    fwrite(STDERR, "ERREUR : créez .env.prod.local\n");
    fwrite(STDERR, "  cp .env.prod.local.example .env.prod.local && nano .env.prod.local\n");
    exit(1);
}

$php = escapeshellarg(PHP_BINARY);
$composer = 'composer';
if (is_file($root.'/composer.phar')) {
    $composer = $php.' '.escapeshellarg($root.'/composer.phar');
}

// --no-scripts : évite cache:clear auto en APP_ENV=dev pendant install
$steps = [
    $composer.' install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-scripts',
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
echo "Vérifier APP_ENV=prod dans .env.prod.local\n";
echo "Cron : 0 3 * * * cd {$root} && {$php} bin/console app:subscriptions:enforce --env=prod --no-debug\n";
