<?php

namespace App\Support;

/** "1 tema", "3 temas", "1.204 temas". */
final class Plural
{
    public static function of(int $count, string $one, string $many): string
    {
        return number_format($count, 0, ',', '.').' '.($count === 1 ? $one : $many);
    }
}
