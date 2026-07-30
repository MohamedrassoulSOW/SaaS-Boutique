<?php

namespace App\Service;

use App\Entity\Product;

/**
 * Génère une image produit simple (JPEG) pour la démo / produits sans photo.
 */
class ProductPhotoGenerator
{
    public function applyPlaceholder(Product $product): bool
    {
        if (!\function_exists('imagecreatetruecolor')) {
            return false;
        }

        $width = 480;
        $height = 480;
        $image = imagecreatetruecolor($width, $height);
        if ($image === false) {
            return false;
        }

        $hash = md5($product->getName().'|'.($product->getReference() ?? ''));
        $r = hexdec(substr($hash, 0, 2));
        $g = hexdec(substr($hash, 2, 2));
        $b = hexdec(substr($hash, 4, 2));
        // Tons doux, lisibles
        $r = (int) (90 + ($r % 120));
        $g = (int) (110 + ($g % 100));
        $b = (int) (100 + ($b % 110));

        $bg = imagecolorallocate($image, $r, $g, $b);
        $fg = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocate($image, min(255, $r + 35), min(255, $g + 35), min(255, $b + 35));
        imagefilledrectangle($image, 0, 0, $width, $height, $bg);
        imagefilledellipse($image, (int) ($width * 0.72), (int) ($height * 0.28), 220, 220, $soft);

        $label = mb_strtoupper(mb_substr($product->getName(), 0, 18));
        $ref = $product->getReference() ?: 'PROD';
        imagestring($image, 5, 28, (int) ($height / 2 - 20), $this->ascii($label), $fg);
        imagestring($image, 3, 28, (int) ($height / 2 + 12), $this->ascii($ref), $fg);

        ob_start();
        imagejpeg($image, null, 82);
        $data = ob_get_clean();
        imagedestroy($image);

        if ($data === false || $data === '') {
            return false;
        }

        $product->setPhotoData($data);
        $product->setPhotoMime('image/jpeg');
        $product->setPhotoName(($product->getReference() ?: 'product').'.jpg');

        return true;
    }

    private function ascii(string $text): string
    {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $converted = $converted === false ? $text : $converted;

        return substr(preg_replace('/[^A-Za-z0-9 .\\-]/', '', $converted) ?: 'PRODUIT', 0, 22);
    }
}
