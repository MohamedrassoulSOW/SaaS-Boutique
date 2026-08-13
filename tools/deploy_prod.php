#!/usr/bin/env php
<?php

/**
 * Déploiement production NdamStore
 * Usage (sur le serveur, à la racine du projet) :
 *   php tools/deploy_prod.php
 *
 * Prérequis :
 *   - PHP ≥ 8.4
 *   - Document root Apache/Nginx → public/
 *   - Fichier .env.prod.local renseigné (secrets)
 */

declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

function run(string $cmd): int
{
    echo "\n> {$cmd}\n";
    passthru($cmd, $code);

    return (int) $code;
}

echo "=== NdamStore deploy prod ===\n";

if (PHP_VERSION_ID < 80400) {
    fwrite(STDERR, 'ERREUR : PHP ≥ 8.4 requis (actuel '.PHP_VERSION.").\n");
    exit(1);
}

if (!is_file($root.'/.env.prod.local')) {
    fwrite(STDERR, "ERREUR : créez .env.prod.local (cp .env.prod.local.example .env.prod.local) et renseignez les secrets.\n");
    exit(1);
}

$steps = [
    'composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction',
    'php bin/console doctrine:migrations:migrate --no-interaction --env=prod',
    'php bin/console app:sessions:init --env=prod --no-debug',
    'php bin/console cache:clear --env=prod --no-debug',
    'php bin/console cache:warmup --env=prod --no-debug',
    'php bin/console assets:install public --env=prod --no-debug',
    'php bin/console app:prod:check --env=prod --no-debug',
];

foreach ($steps as $step) {
    $code = run($step);
    if ($code !== 0) {
        fwrite(STDERR, "Échec (code {$code}) : {$step}\n");
        exit($code);
    }
}

echo "\n=== Déploiement terminé ===\n";
echo "Document root → public/\n";
echo "Test mail     → php bin/console mailer:test contact@ndamstore.sowcoder.com --env=prod\n";
echo "Cron (cron)   → 0 3 * * * cd {$root} && php bin/console app:subscriptions:enforce --env=prod --no-debug\n";
echo "Wave / OM     → +221 77 790 14 60\n";
