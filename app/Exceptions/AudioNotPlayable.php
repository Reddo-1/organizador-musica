<?php

namespace App\Exceptions;

use App\Models\Song;
use RuntimeException;

class AudioNotPlayable extends RuntimeException
{
    public static function missing(Song $song): self
    {
        return new self("No se encuentra el archivo de «{$song->displayName()}».");
    }

    public static function needsFfmpeg(Song $song): self
    {
        return new self('Configura ffmpeg para escucharlo aquí.');
    }

    public static function conversionFailed(Song $song, string $detail): self
    {
        return new self("ffmpeg no pudo convertir «{$song->displayName()}»: {$detail}");
    }
}
