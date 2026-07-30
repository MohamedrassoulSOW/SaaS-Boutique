<?php

declare(strict_types=1);

$srcPath = __DIR__ . '/../public/images/ndamstore-logo.png';
$outDir = __DIR__ . '/../public/icons';
if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

$bytes = file_get_contents($srcPath);
if ($bytes === false) {
    fwrite(STDERR, "Cannot read logo\n");
    exit(1);
}

$src = imagecreatefromstring($bytes);
if ($src === false) {
    fwrite(STDERR, "Cannot decode logo (expected PNG/JPEG/WebP)\n");
    exit(1);
}

$make = static function (int $size, string $filename, float $padRatio) use ($src, $outDir): void {
    $out = imagecreatetruecolor($size, $size);
    imagealphablending($out, true);
    imagesavealpha($out, false);
    $bg = imagecolorallocate($out, 12, 92, 80);
    imagefilledrectangle($out, 0, 0, $size, $size, $bg);

    $sw = imagesx($src);
    $sh = imagesy($src);
    $pad = (int) ($size * $padRatio);
    $dw = $size - 2 * $pad;
    $dh = $size - 2 * $pad;
    $scale = min($dw / $sw, $dh / $sh);
    $nw = (int) ($sw * $scale);
    $nh = (int) ($sh * $scale);
    $dx = (int) (($size - $nw) / 2);
    $dy = (int) (($size - $nh) / 2);
    imagecopyresampled($out, $src, $dx, $dy, 0, 0, $nw, $nh, $sw, $sh);
    imagepng($out, $outDir . '/' . $filename);
    imagedestroy($out);
};

$make(192, 'icon-192.png', 0.14);
$make(512, 'icon-512.png', 0.14);
$make(180, 'apple-touch-icon.png', 0.14);
$make(512, 'maskable-512.png', 0.22);

imagedestroy($src);
echo "PWA icons generated\n";
