<?php

namespace App\Exceptions;

use RuntimeException;

class UnreadableAudioFile extends RuntimeException
{
    public static function notFound(string $path): self
    {
        // The path is not in the message: every caller shows it next to it.
        return new self('No se encuentra el archivo o no se puede abrir.');
    }

    public static function because(string $path, string $reason): self
    {
        return new self("No se pudo leer: {$reason}");
    }
}
