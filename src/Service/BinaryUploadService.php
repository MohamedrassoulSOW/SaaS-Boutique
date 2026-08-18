<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class BinaryUploadService
{
    private const MAX_BYTES = 2_000_000; // 2 Mo
    private const ALLOWED_MIME = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];
    private const ALLOWED_EXT = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @return array{data: string, mime: string, name: string}
     */
    public function readImage(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide.');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Image trop volumineuse (max 2 Mo).');
        }

        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext === '' || !\in_array($ext, self::ALLOWED_EXT, true)) {
            throw new \InvalidArgumentException('Extension non autorisée (JPEG, PNG, WEBP, GIF).');
        }

        $path = $file->getPathname();
        $detected = null;
        if (\function_exists('finfo_open')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->file($path) ?: null;
        }
        if ($detected === null || $detected === false) {
            $detected = $file->getMimeType() ?: 'application/octet-stream';
        }
        if (!\in_array($detected, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Format image non autorisé (JPEG, PNG, WEBP, GIF).');
        }

        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Fichier image illisible ou corrompu.');
        }

        if ($imageInfo[0] > 4096 || $imageInfo[1] > 4096) {
            throw new \RuntimeException('Les dimensions de l\'image ne doivent pas dépasser 4096×4096 pixels.');
        }

        $typeMime = $imageInfo['mime'] ?? null;
        if (!\is_string($typeMime) || !\in_array($typeMime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Contenu image non autorisé.');
        }

        // Cohérence extension / contenu (évite polyglots basiques)
        $expected = match ($typeMime) {
            'image/jpeg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            'image/gif' => ['gif'],
            default => [],
        };
        if ($expected !== [] && !\in_array($ext, $expected, true)) {
            throw new \InvalidArgumentException('Extension incohérente avec le contenu du fichier.');
        }

        $data = file_get_contents($path);
        if ($data === false || $data === '') {
            throw new \InvalidArgumentException('Impossible de lire le fichier.');
        }

        // Rejette les payloads trop « texte » (SVG polyglot / HTML embarqué)
        $head = strtolower(substr($data, 0, 256));
        if (str_contains($head, '<script') || str_contains($head, '<?php') || str_contains($head, '<html')) {
            throw new \InvalidArgumentException('Fichier image suspect rejeté.');
        }

        return [
            'data' => $data,
            'mime' => $typeMime,
            'name' => $file->getClientOriginalName(),
        ];
    }
}
