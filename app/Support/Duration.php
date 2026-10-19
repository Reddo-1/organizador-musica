<?php

namespace App\Support;

/** "1 h 32 min", "48 min", "3:05" for a song. */
final class Duration
{
    public static function human(float $seconds): string
    {
        $minutes = (int) round($seconds / 60);

        if ($minutes < 60) {
            return "{$minutes} min";
        }

        return intdiv($minutes, 60).' h '.($minutes % 60).' min';
    }

    public static function clock(?float $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) round($seconds);

        return $seconds >= 3600 ? gmdate('G:i:s', $seconds) : intdiv($seconds, 60).':'.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT);
    }
}
