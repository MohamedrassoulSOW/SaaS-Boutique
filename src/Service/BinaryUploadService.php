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

    /**
     * @return array{data: string, mime: string, name: string}
     */
    public function readImage(UploadedFile $file): array
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Fichier invalide.');
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        if (!\in_array($mime, self::ALLOWED_MIME, true)) {
            throw new \InvalidArgumentException('Format image non autorisé (JPEG, PNG, WEBP, GIF).');
        }

        if ($file->getSize() > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Image trop volumineuse (max 2 Mo).');
        }

        $data = file_get_contents($file->getPathname());
        if ($data === false || $data === '') {
            throw new \InvalidArgumentException('Impossible de lire le fichier.');
        }

        return [
            'data' => $data,
            'mime' => $mime,
            'name' => $file->getClientOriginalName(),
        ];
    }
}
