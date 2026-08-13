<?php

declare(strict_types=1);

use App\Entity\Subscription;

require dirname(__DIR__).'/vendor/autoload.php';

$failures = 0;
$check = static function (bool $ok, string $label) use (&$failures): void {
    if ($ok) {
        echo "[OK] $label\n";

        return;
    }
    ++$failures;
    echo "[FAIL] $label\n";
};

$check(Subscription::catalogPrice(Subscription::PLAN_FREE) === '0.00', 'Prix Gratuit = 0');
$check(Subscription::catalogPrice(Subscription::PLAN_BASIC) === '15000.00', 'Prix Basique = 15000');
$check(Subscription::catalogPrice(Subscription::PLAN_PRO) === '25000.00', 'Prix Pro = 25000');
$check(Subscription::planLabel(Subscription::PLAN_BASIC) === 'Basique', 'Libellé Basique');

$required = [
    'src/Controller/ExpenseController.php',
    'src/Controller/BillingController.php',
    'src/Entity/CustomerPayment.php',
    'src/Entity/Expense.php',
    'templates/marketing/legal_terms.html.twig',
    'templates/marketing/legal_privacy.html.twig',
    'templates/marketing/legal_mentions.html.twig',
    'migrations/Version20260813180000.php',
    'migrations/Version20260813190000.php',
    'src/Security/UserChecker.php',
    'config/packages/monolog.yaml',
];
foreach ($required as $file) {
    $check(is_file(dirname(__DIR__).'/'.$file), "Fichier présent : $file");
}

exit($failures > 0 ? 1 : 0);
