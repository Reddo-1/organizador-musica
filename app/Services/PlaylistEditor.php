<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes to a generated playlist (SPEC §5.7): rename, take songs out, drag them into another
 * place, and its total duration and energy curve.
 */
final class PlaylistEditor
{
    public function __construct(private readonly M3u8Exporter $exporter) {}

    public function rename(Playlist $playlist, string $name): Playlist
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));

        if ($name === '') {
            throw new InvalidArgumentException('La playlist necesita un nombre.');
        }

        $playlist->update(['name' => $name]);

        return $playlist;
    }

    public function remove(Playlist $playlist, int $songId): void
    {
        DB::transaction(function () use ($playlist, $songId): void {
            $playlist->songs()->detach($songId);
            $this->renumber($playlist, $playlist->songs()->pluck('songs.id')->all());
        });
    }

    /** Moves a song to $position (0 = first), as the list is dragged. */
    public function move(Playlist $playlist, int $songId, int $position): void
    {
        $ids = $playlist->songs()->pluck('songs.id')->all();
        $from = array_search($songId, $ids, true);

        if ($from === false) {
            return;
        }

        array_splice($ids, $from, 1);
        array_splice($ids, max(0, min($position, count($ids))), 0, [$songId]);

        DB::transaction(fn () => $this->renumber($playlist, $ids));
    }

    /** @return array{seconds: float, unknown: int} Total length, and how many songs have none. */
    public function duration(Playlist $playlist): array
    {
        $durations = $playlist->songs()->pluck('duration');

        return [
            'seconds' => (float) $durations->sum(),
            'unknown' => $durations->filter(fn ($duration): bool => $duration === null)->count(),
        ];
    }

    /** @return list<?int> The energy of each position, null where a song has none. */
    public function energyCurve(Playlist $playlist): array
    {
        return $playlist->songs()->pluck('energy')->map(fn ($energy): ?int => $energy === null ? null : (int) $energy)->all();
    }

    /** Saves the .m3u8 in the playlists folder and returns where it went. */
    public function export(Playlist $playlist, ?string $folder = null): string
    {
        return $this->exporter->save($playlist->name, $this->songs($playlist), $folder);
    }

    public function m3u8(Playlist $playlist): string
    {
        return $this->exporter->render($this->songs($playlist));
    }

    /** @return Collection<int, Song> */
    public function songs(Playlist $playlist): Collection
    {
        return $playlist->songs()->get();
    }

    /** @param  list<int>  $ids */
    private function renumber(Playlist $playlist, array $ids): void
    {
        foreach ($ids as $index => $id) {
            $playlist->songs()->updateExistingPivot($id, ['position' => $index + 1]);
        }

        $playlist->touch();
    }
}
