<?php

namespace App\Services;

use App\Models\Song;
use App\Support\NameKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Manual relocation of songs whose file is missing (SPEC §5.3). Never touches a file.
 */
final class SongRelocator
{
    /**
     * The present songs whose file name looks most like the missing one, most alike first.
     *
     * @param  Collection<int, Song>|null  $present  The present songs, when the caller already has them.
     * @return Collection<int, array{song: Song, similarity: int}>
     */
    public function suggestions(Song $missing, int $limit = 8, ?Collection $present = null): Collection
    {
        $name = self::comparable($missing->fileName());

        return ($present ?? Song::query()->present()->get())
            ->reject(fn (Song $song): bool => $song->is($missing) || $song->missing)
            ->map(function (Song $song) use ($name): array {
                similar_text($name, self::comparable($song->fileName()), $percent);

                return ['song' => $song, 'similarity' => (int) round($percent)];
            })
            ->sortBy([['similarity', 'desc'], ['song.path', 'asc']])
            ->take($limit)
            ->values();
    }

    /**
     * The missing song takes the chosen song's path and that duplicate is deleted. Tags are
     * joined, the duplicate's genre is used only if the original had none, and the duration
     * comes from the new file. Playlists that held the duplicate keep the original instead.
     */
    public function relocate(Song $missing, Song $duplicate): Song
    {
        return DB::transaction(function () use ($missing, $duplicate): Song {
            $missing->tags()->syncWithoutDetaching($duplicate->tags()->pluck('tags.id'));

            foreach ($duplicate->playlists as $playlist) {
                if (! $playlist->songs()->whereKey($missing->getKey())->exists()) {
                    $playlist->songs()->attach($missing->getKey(), ['position' => $playlist->pivot->position]);
                }
            }

            $path = $duplicate->path;
            $missing->genre_id ??= $duplicate->genre_id;
            $missing->duration = $duplicate->duration;

            // Deleting first frees the unique path_key for the original.
            $duplicate->delete();

            $missing->path = $path;
            $missing->missing = false;
            $missing->save();

            return $missing;
        });
    }

    /** Removes the song from the app only; the file, if it exists somewhere, is not touched. */
    public function remove(Song $song): void
    {
        $song->delete();
    }

    private static function comparable(string $fileName): string
    {
        $dot = strrpos($fileName, '.');

        return NameKey::of($dot > 0 ? substr($fileName, 0, $dot) : $fileName);
    }
}
