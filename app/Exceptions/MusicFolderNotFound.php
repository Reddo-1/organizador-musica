<?php

namespace App\Exceptions;

use RuntimeException;

class MusicFolderNotFound extends RuntimeException
{
    public static function at(string $folder): self
    {
        return new self("No existe la carpeta de música {$folder}. Revísala en Ajustes.");
    }
}
