<?php

/**
 * Génère les slides PNG du guide vidéo NdamStore (1280×720).
 *
 * Usage: php tools/build_guide_video_slides.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$outDir = $root.'/tools/guide-video-build/slides';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Impossible de créer {$outDir}\n");
    exit(1);
}

$chapters = [
    ['num' => '01', 'kicker' => 'Introduction', 'title' => "Bienvenue sur\nNdamStore", 'lines' => ['La gestion de boutique,', 'simple et claire.']],
    ['num' => '02', 'kicker' => 'Étape 1', 'title' => "Connectez-vous\nà votre espace", 'lines' => ['E-mail du compte', 'Réinitialisation : 30 min']],
    ['num' => '03', 'kicker' => 'Étape 2', 'title' => "Configurez\nvotre entreprise", 'lines' => ['TVA · HT / TTC', 'NINEA']],
    ['num' => '04', 'kicker' => 'Étape 3', 'title' => "Ajoutez\nvos produits", 'lines' => ['Prix de vente', 'Prix d’achat (bénéfices)']],
    ['num' => '05', 'kicker' => 'Étape 4', 'title' => "Ouvrez\nla caisse", 'lines' => ['Fond de caisse', 'Obligatoire avant vente']],
    ['num' => '06', 'kicker' => 'Étape 5', 'title' => "Réalisez votre\npremière vente", 'lines' => ['POS · code-barres', 'Espèces · Wave · OM · crédit']],
    ['num' => '07', 'kicker' => 'Étape 6', 'title' => "Clients\net crédits", 'lines' => ['Fiche client', 'Solde dû & paiements']],
    ['num' => '08', 'kicker' => 'Étape 7', 'title' => "Lisez vos\nbénéfices", 'lines' => ['CA − coût d’achat', 'Filtres & export CSV']],
    ['num' => '09', 'kicker' => 'Étape 8', 'title' => "Invitez\nvotre équipe", 'lines' => ['Caissier · Responsable', 'Magasinier']],
    ['num' => '10', 'kicker' => 'Conclusion', 'title' => "Vous êtes\nprêt", 'lines' => ['Guide : /guide', '+221 77 790 14 60']],
];

$w = 1280;
$h = 720;
$font = 'C:/Windows/Fonts/segoeuib.ttf';
$fontReg = 'C:/Windows/Fonts/segoeui.ttf';
if (!is_file($font)) {
    $font = 'C:/Windows/Fonts/arialbd.ttf';
}
if (!is_file($fontReg)) {
    $fontReg = 'C:/Windows/Fonts/arial.ttf';
}

foreach ($chapters as $i => $c) {
    $im = imagecreatetruecolor($w, $h);
    if ($im === false) {
        fwrite(STDERR, "GD indisponible\n");
        exit(1);
    }

    // Fond teal NdamStore
    for ($y = 0; $y < $h; ++$y) {
        $t = $y / ($h - 1);
        $r = (int) (10 + (20 - 10) * $t);
        $g = (int) (63 + (122 - 63) * $t);
        $b = (int) (55 + (106 - 55) * $t);
        $col = imagecolorallocate($im, $r, $g, $b);
        imageline($im, 0, $y, $w, $y, $col);
    }

    $white = imagecolorallocate($im, 244, 250, 247);
    $muted = imagecolorallocate($im, 190, 220, 210);
    $accent = imagecolorallocatealpha($im, 255, 255, 255, 100);

    // Cercle décoratif
    imagefilledellipse($im, 1100, 160, 380, 380, $accent);
    imagefilledellipse($im, -40, 620, 320, 320, $accent);

    // Badge
    imagefilledrectangle($im, 72, 64, 340, 108, imagecolorallocatealpha($im, 255, 255, 255, 100));
    imagettftext($im, 15, 0, 92, 94, $white, $fontReg, 'GUIDE VIDÉO · FRANÇAIS');

    imagettftext($im, 18, 0, 72, 160, $muted, $fontReg, $c['kicker'].'  ·  '.$c['num'].'/10');

    $titleY = 220;
    foreach (explode("\n", $c['title']) as $line) {
        imagettftext($im, 48, 0, 72, $titleY, $white, $font, $line);
        $titleY += 68;
    }

    $lineY = $titleY + 30;
    foreach ($c['lines'] as $line) {
        imagettftext($im, 22, 0, 72, $lineY, $muted, $fontReg, '•  '.$line);
        $lineY += 42;
    }

    imagettftext($im, 16, 0, 72, 670, $muted, $font, 'NdamStore');
    imagettftext($im, 14, 0, 72, 698, $muted, $fontReg, 'La réussite de votre commerce.');

    $path = sprintf('%s/slide-%02d.png', $outDir, $i + 1);
    imagepng($im, $path);
    imagedestroy($im);
    echo "OK {$path}\n";
}

echo "Slides générées : ".count($chapters)."\n";
