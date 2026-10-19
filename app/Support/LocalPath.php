<?php

namespace App\Support;

use Normalizer;

/**
 * Paths are stored the way Windows writes them (SPEC §4), "C:\Users\...", because that is what
 * rekordbox reads from the .m3u8 and what the Python version stored. When the app itself runs in
 * WSL those paths are reachable as /mnt/c/..., so every disk access goes through toLocal() and
 * every path found on disk is stored through toStored(). On Windows both are (almost) no-ops.
 */
final class LocalPath
{
    private static ?bool $detectedWsl = null;

    public static function isWsl(): bool
    {
        $configured = config('music.wsl');

        if ($configured !== null && $configured !== '') {
            return filter_var($configured, FILTER_VALIDATE_BOOL);
        }

        return self::$detectedWsl ??= self::detectWsl();
    }

    /** The path to hand to PHP's filesystem functions on this machine. */
    public static function toLocal(string $stored): string
    {
        if (! self::isWsl()) {
            return $stored;
        }

        if (preg_match('/^([A-Za-z]):(?:[\\\\\/](.*))?$/s', $stored, $match)) {
            $rest = trim(str_replace('\\', '/', $match[2] ?? ''), '/');

            return '/mnt/'.strtolower($match[1]).($rest === '' ? '' : '/'.$rest);
        }

        // \\wsl.localhost\Ubuntu\home\... (how Windows names the WSL disk) is a plain Linux path here.
        if (preg_match('/^\\\\\\\\wsl(?:\.localhost|\$)\\\\[^\\\\]+(\\\\.*)?$/i', $stored, $match)) {
            return str_replace('\\', '/', $match[1] ?? '') ?: '/';
        }

        return $stored;
    }

    /** The path as it is stored and shown: drive paths in Windows form, with backslashes. */
    public static function toStored(string $local): string
    {
        if (self::isWsl() && preg_match('#^/mnt/([a-z])(?:/(.*))?$#s', $local, $match)) {
            return strtoupper($match[1]).':\\'.str_replace('/', '\\', $match[2] ?? '');
        }

        if (preg_match('/^[A-Za-z]:[\\\\\/]/', $local)) {
            return str_replace('/', '\\', $local);
        }

        return $local;
    }

    /** SPEC §4: "\" as separator and lower case, because Windows ignores case in paths. */
    public static function key(string $stored): string
    {
        return mb_strtolower(self::nfc(str_replace('/', '\\', $stored)));
    }

    public static function fileName(string $path): string
    {
        $parts = preg_split('#[\\\\/]#', rtrim($path, '\\/'));

        return (string) end($parts);
    }

    /** The file name without its extension: "Artist - Title" for "C:\DNB\Artist - Title.mp3". */
    public static function stem(string $path): string
    {
        $name = self::fileName($path);
        $dot = strrpos($name, '.');

        return $dot > 0 ? substr($name, 0, $dot) : $name;
    }

    public static function extension(string $path): string
    {
        $name = self::fileName($path);
        $dot = strrpos($name, '.');

        return $dot > 0 ? strtolower(substr($name, $dot + 1)) : '';
    }

    public static function nfc(string $value): string
    {
        if (! class_exists(Normalizer::class)) {
            return $value;
        }

        return Normalizer::normalize($value, Normalizer::FORM_C) ?: $value;
    }

    private static function detectWsl(): bool
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            return false;
        }

        if (getenv('WSL_DISTRO_NAME') !== false) {
            return true;
        }

        $version = @file_get_contents('/proc/version');

        return is_string($version) && str_contains(strtolower($version), 'microsoft');
    }
}
