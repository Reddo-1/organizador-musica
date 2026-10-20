<?php

namespace App\Services;

use App\Support\LocalPath;

/**
 * Walks the disks of this PC so a folder can be chosen with clicks instead of typing its path. A
 * browser never tells a page the absolute path of a folder, but the app runs on the same PC, so it
 * can list them itself. Only folder names are read; no file is opened. Paths go in and out the
 * way they are stored (SPEC §4): "C:\Users\...", also when the app runs in WSL.
 */
final class FolderBrowser
{
    /** Folders nobody keeps music in, which Windows puts at the root of every disk. */
    private const HIDDEN = ['system volume information', 'recovery', 'config.msi', 'msocache', 'perflogs'];

    /**
     * Where browsing starts: the drives of Windows (also seen from WSL) and, outside Windows, "/".
     *
     * @return list<array{name: string, path: string}>
     */
    public function roots(): array
    {
        $roots = [];

        if (PHP_OS_FAMILY === 'Windows' || LocalPath::isWsl()) {
            foreach (range('A', 'Z') as $letter) {
                if (@is_dir(LocalPath::toLocal("{$letter}:\\"))) {
                    $roots[] = ['name' => "{$letter}:", 'path' => "{$letter}:\\"];
                }
            }
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            $roots[] = ['name' => '/', 'path' => '/'];
        }

        return $roots;
    }

    public function exists(string $path): bool
    {
        return $path !== '' && @is_dir(LocalPath::toLocal($path));
    }

    /**
     * The subfolders of a folder in natural order, ignoring case; hidden and system ones left out.
     *
     * @return list<array{name: string, path: string}>
     */
    public function children(string $path): array
    {
        $local = LocalPath::toLocal($path);
        $names = @scandir($local);

        if ($names === false) {
            return [];
        }

        $folders = [];

        foreach ($names as $name) {
            if ($name === '.' || $name === '..' || str_starts_with($name, '.') || str_starts_with($name, '$')
                || in_array(mb_strtolower($name), self::HIDDEN, true)
                || ! @is_dir($local.DIRECTORY_SEPARATOR.$name)) {
                continue;
            }

            $folders[] = ['name' => LocalPath::nfc($name), 'path' => $this->join($path, LocalPath::nfc($name))];
        }

        usort($folders, fn (array $a, array $b): int => strnatcasecmp($a['name'], $b['name']));

        return $folders;
    }

    /** The folder that holds this one, or null for a drive or "/". */
    public function parent(string $path): ?string
    {
        if ($this->isRoot($path)) {
            return null;
        }

        $windows = $this->isWindowsPath($path);
        $trimmed = rtrim($path, '\\/');
        $cut = max((int) strrpos($trimmed, '\\'), (int) strrpos($trimmed, '/'));
        $parent = substr($trimmed, 0, $cut);

        if ($windows && preg_match('/^[A-Za-z]:$/', $parent)) {
            return $parent.'\\';
        }

        return $parent === '' ? '/' : $parent;
    }

    public function isRoot(string $path): bool
    {
        return $path === '/' || (bool) preg_match('/^[A-Za-z]:[\\\\\/]?$/', $path);
    }

    private function join(string $path, string $name): string
    {
        $separator = $this->isWindowsPath($path) ? '\\' : '/';

        return rtrim($path, '\\/').$separator.$name;
    }

    private function isWindowsPath(string $path): bool
    {
        return (bool) preg_match('/^[A-Za-z]:/', $path) || str_starts_with($path, '\\\\');
    }
}
