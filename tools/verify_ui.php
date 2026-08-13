<?php
$css = file_get_contents(__DIR__ . '/../public/css/app.css');
$js = file_get_contents(__DIR__ . '/../public/js/app.js');
$ht = file_get_contents(__DIR__ . '/../.htaccess');
$checks = [
    'confirm z-index 1110' => (bool) preg_match('/#appConfirmModal\s*\{\s*z-index:\s*1110/', $css),
    'result z-index 1210' => (bool) preg_match('/#appResultModal\s*\{\s*z-index:\s*1210/', $css),
    'backdrop under modals' => str_contains($css, 'z-index: 1090 !important'),
    'ensureModalsOnBody' => str_contains($js, 'ensureModalsOnBody'),
    'htaccess -> public' => str_contains($ht, 'public/$1'),
    'root index.php' => is_file(__DIR__ . '/../index.php'),
    'css cache v40' => str_contains(file_get_contents(__DIR__ . '/../templates/base.html.twig'), 'app.css') && str_contains(file_get_contents(__DIR__ . '/../templates/base.html.twig'), '?v=40'),
    'js cache v7' => str_contains(file_get_contents(__DIR__ . '/../templates/base.html.twig'), 'app.js') && str_contains(file_get_contents(__DIR__ . '/../templates/base.html.twig'), '?v=7'),
];
$fail = 0;
foreach ($checks as $k => $ok) {
    echo ($ok ? '[OK]   ' : '[FAIL] ') . $k . PHP_EOL;
    if (!$ok) {
        ++$fail;
    }
}
$envLocal = file_get_contents(__DIR__ . '/../.env.prod.local') ?: '';
if (str_contains($envLocal, 'USER:PASSWORD') || str_contains($envLocal, 'NDAMSTORE_DB')) {
    echo "[WARN] DATABASE_URL encore placeholder dans .env.prod.local" . PHP_EOL;
} else {
    echo "[OK]   DATABASE_URL renseigné" . PHP_EOL;
}
exit($fail > 0 ? 1 : 0);
