<?php

namespace App\Exceptions;

use RuntimeException;

class UnreadableAudioFile extends RuntimeException
{
    public static function notFound(string $path): self
    {
        return new self("No se encuentra o no se puede leer el archivo: {$path}");
    }

    public static function because(string $path, string $reason): self
    {
        return new self("No se pudo leer {$path}: {$reason}");
    }
}
