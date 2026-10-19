<?php

namespace App\Support;

/**
 * Genre and tag names are unique ignoring case (SPEC §4): "Halftime" and "halftime" are the
 * same tag. SQLite's NOCASE only folds ASCII, so names are compared through this key in PHP,
 * which also folds "Ñ" and "É".
 */
final class NameKey
{
    public static function of(string $name): string
    {
        return mb_strtolower(LocalPath::nfc(self::clean($name)));
    }

    /** Trims and collapses inner whitespace, the form a name is saved in. */
    public static function clean(string $name): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $name));
    }
}
