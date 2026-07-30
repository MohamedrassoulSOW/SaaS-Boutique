<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Contenu binaire stocké en base (images, PDF, etc.) — pas de fichiers sur disque.
 */
trait BinaryPayloadTrait
{
    public function readBinary(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (\is_resource($value)) {
            $content = stream_get_contents($value);

            return $content === false || $content === '' ? null : $content;
        }

        $content = (string) $value;

        return $content === '' ? null : $content;
    }

    public function writeBinary(?string $content): mixed
    {
        if ($content === null || $content === '') {
            return null;
        }

        return $content;
    }
}
