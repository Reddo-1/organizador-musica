<?php

namespace App\Services;

use App\Models\Song;
use App\Support\LocalPath;
use Illuminate\Support\Facades\DB;

/**
 * Moves the whole library to another folder in one go, for when the music lives somewhere else
 * on another computer or disk: every stored path that starts with $from starts with $to instead.
 * The files themselves are not touched.
 */
final class LibraryRelocator
{
    /** @return int How many songs changed. */
    public function relocate(string $from, string $to): int
    {
        $from = rtrim(Settings::folder($from), '\\/');
        $to = rtrim(Settings::folder($to), '\\/');
        $fromKey = LocalPath::key($from);

        return DB::transaction(function () use ($from, $to, $fromKey): int {
            $changed = 0;

            Song::query()->select(['id', 'path', 'path_key', 'missing'])->orderBy('id')->each(function (Song $song) use ($from, $to, $fromKey, &$changed): void {
                $key = $song->path_key;

                if ($key !== $fromKey && ! str_starts_with($key, $fromKey.'\\')) {
                    return;
                }

                $rest = substr($song->path, strlen($from));
                $separator = str_contains($to, '/') && ! preg_match('/^[A-Za-z]:/', $to) ? '/' : '\\';
                $song->path = $to.str_replace(['\\', '/'], $separator, $rest);
                $song->missing = ! file_exists(LocalPath::toLocal($song->path));
                $song->save();
                $changed++;
            });

            return $changed;
        });
    }
}
