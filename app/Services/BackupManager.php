<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Copies of the SQLite database (SPEC §5.10): one a day on the first request of the day, and one
 * whenever the user asks, kept in storage/app/backups. Only the newest few are kept.
 */
final class BackupManager
{
    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /** Only a SQLite database on disk can be copied this way. */
    public function isAvailable(): bool
    {
        $database = (string) DB::connection()->getConfig('database');

        return DB::connection()->getDriverName() === 'sqlite' && $database !== '' && $database !== ':memory:';
    }

    /** Today's copy, made now if it does not exist yet; null when copies are not possible. */
    public function dailyBackup(): ?string
    {
        if (! $this->isAvailable()) {
            return null;
        }

        $file = $this->directory().DIRECTORY_SEPARATOR.'music_'.now()->format('Y-m-d').'.sqlite';

        if (! is_file($file)) {
            $this->copyTo($file);
        }

        return $file;
    }

    /** "Copia ahora": a copy named with the time, so it never clashes with today's. */
    public function backupNow(): string
    {
        if (! $this->isAvailable()) {
            throw new RuntimeException('Las copias solo funcionan con SQLite.');
        }

        $file = $this->directory().DIRECTORY_SEPARATOR.'music_'.now()->format('Y-m-d_His').'.sqlite';
        $this->copyTo($file);

        return $file;
    }

    /** @return list<array{path: string, name: string, size: int, time: int}> Newest first. */
    public function backups(): array
    {
        return collect(File::glob($this->directory().DIRECTORY_SEPARATOR.'music_*.sqlite'))
            ->map(fn (string $path): array => [
                'path' => $path,
                'name' => basename($path),
                'size' => (int) filesize($path),
                'time' => (int) filemtime($path),
            ])
            ->sortByDesc(fn (array $backup): string => $backup['time'].$backup['name'])
            ->values()
            ->all();
    }

    private function copyTo(string $file): void
    {
        File::ensureDirectoryExists($this->directory());

        // VACUUM INTO writes a consistent, compacted copy even while the app is in use.
        DB::statement('VACUUM INTO ?', [$file]);

        $this->prune();
    }

    private function prune(): void
    {
        foreach (array_slice($this->backups(), (int) config('music.backups_to_keep')) as $old) {
            File::delete($old['path']);
        }
    }
}
