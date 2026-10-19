<?php

namespace App\Services;

use App\Data\ScanResult;
use App\Models\Setting;
use App\Support\LocalPath;
use Carbon\CarbonImmutable;

/**
 * The settings editable from Ajustes (SPEC §3). The table holds only what the user changed;
 * everything else comes from config/music.php.
 */
final class Settings
{
    public const MUSIC_FOLDER = 'music_folder';

    public const PLAYLISTS_FOLDER = 'playlists_folder';

    public const DEFAULT_GENRE = 'default_genre';

    public const SUBFOLDER_AS_SUBGENRE = 'subfolder_as_subgenre';

    /** The summary of the last scan, so Escanear still shows it after a reload. */
    public const LAST_SCAN = 'last_scan';

    /** @var array<string, string|null>|null */
    private ?array $values = null;

    public function musicFolder(): string
    {
        return $this->filled(self::MUSIC_FOLDER) ?? config('music.default_folder');
    }

    public function playlistsFolder(): string
    {
        return $this->filled(self::PLAYLISTS_FOLDER) ?? config('music.playlists_folder');
    }

    /** Null when the user left it empty: then the genre comes from each file's tags (SPEC §5.2). */
    public function defaultGenre(): ?string
    {
        $value = $this->has(self::DEFAULT_GENRE)
            ? $this->get(self::DEFAULT_GENRE)
            : config('music.default_genre');

        return filled($value) ? trim($value) : null;
    }

    public function subfolderAsSubgenre(): bool
    {
        return $this->has(self::SUBFOLDER_AS_SUBGENRE)
            ? $this->get(self::SUBFOLDER_AS_SUBGENRE) === '1'
            : (bool) config('music.subfolder_as_subgenre');
    }

    public function setMusicFolder(string $folder): void
    {
        $this->put(self::MUSIC_FOLDER, self::folder($folder));
    }

    public function setPlaylistsFolder(?string $folder): void
    {
        $this->put(self::PLAYLISTS_FOLDER, filled($folder) ? self::folder($folder) : null);
    }

    public function setDefaultGenre(?string $genre): void
    {
        $this->put(self::DEFAULT_GENRE, trim((string) $genre));
    }

    public function setSubfolderAsSubgenre(bool $enabled): void
    {
        $this->put(self::SUBFOLDER_AS_SUBGENRE, $enabled ? '1' : '0');
    }

    /** @return array{result: ScanResult, at: CarbonImmutable}|null */
    public function lastScan(): ?array
    {
        $value = json_decode((string) $this->get(self::LAST_SCAN), true);

        if (! is_array($value) || ! isset($value['result'], $value['at'])) {
            return null;
        }

        return ['result' => ScanResult::fromArray($value['result']), 'at' => CarbonImmutable::parse($value['at'])];
    }

    public function setLastScan(ScanResult $result): void
    {
        $this->put(self::LAST_SCAN, json_encode(
            ['result' => $result->toArray(), 'at' => now()->toIso8601String()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    /** "C:/Música/DNB/" and "/mnt/c/Música/DNB" are both kept as "C:\Música\DNB". */
    public static function folder(string $folder): string
    {
        $folder = LocalPath::toStored(trim($folder));

        return preg_match('/^[A-Za-z]:\\\\$/', $folder) ? $folder : rtrim($folder, '\\/');
    }

    private function put(string $key, ?string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        $this->values = null;
    }

    private function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    private function get(string $key): ?string
    {
        return $this->all()[$key] ?? null;
    }

    private function filled(string $key): ?string
    {
        $value = $this->get($key);

        return filled($value) ? $value : null;
    }

    /** @return array<string, string|null> */
    private function all(): array
    {
        return $this->values ??= Setting::query()->pluck('value', 'key')->all();
    }
}
