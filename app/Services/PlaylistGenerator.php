<?php

namespace App\Services;

use App\Models\Playlist;
use App\Models\Song;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Random\Randomizer;

/**
 * Builds a set from the filters (SPEC §5.7): when more songs match than asked for, a random
 * sample; then the chosen order, with ties left in random order and songs without the value last.
 */
final class PlaylistGenerator
{
    /** @var array<string, string> */
    public const ORDERS = [
        'random' => 'Aleatorio',
        'energy_asc' => 'Energía ↑',
        'energy_desc' => 'Energía ↓',
        'song_year_asc' => 'Año de la canción ↑',
        'discovered_year_asc' => 'Año de descubrimiento ↑',
        'bpm_asc' => 'BPM ↑',
    ];

    private readonly Randomizer $randomizer;

    public function __construct(private readonly SongFilter $filter, ?Randomizer $randomizer = null)
    {
        $this->randomizer = $randomizer ?? new Randomizer;
    }

    /**
     * The ids of the chosen songs, in playlist order. $count 0 means every matching song.
     *
     * @param  array<string, mixed>  $filters
     * @return list<int>
     */
    public function pick(array $filters, int $count, string $order): array
    {
        if (! array_key_exists($order, self::ORDERS)) {
            throw new InvalidArgumentException("El orden {$order} no existe.");
        }

        $songs = $this->filter->apply($filters)->get(['id', 'energy', 'song_year', 'discovered_year', 'bpm'])->all();
        $songs = $this->randomizer->shuffleArray($songs);

        if ($count > 0) {
            $songs = array_slice($songs, 0, $count);
        }

        if ($order !== 'random') {
            [$field, $direction] = [substr($order, 0, strrpos($order, '_')), substr($order, strrpos($order, '_') + 1)];

            // usort is stable, so songs that tie keep the random order of the shuffle.
            usort($songs, function (Song $a, Song $b) use ($field, $direction): int {
                $x = $a->{$field};
                $y = $b->{$field};

                return match (true) {
                    $x === null && $y === null => 0,
                    $x === null => 1,
                    $y === null => -1,
                    default => $direction === 'desc' ? $y <=> $x : $x <=> $y,
                };
            });
        }

        return array_map(fn (Song $song): int => $song->id, $songs);
    }

    /**
     * Fills $draft with a new combination, or creates a draft named "Set AAAA-MM-DD".
     *
     * @param  array<string, mixed>  $filters
     */
    public function generate(array $filters, int $count, string $order, ?Playlist $draft = null): Playlist
    {
        $ids = $this->pick($filters, $count, $order);

        return DB::transaction(function () use ($filters, $count, $order, $draft, $ids): Playlist {
            $draft ??= Playlist::create(['name' => self::defaultName()]);
            $draft->update(['filters' => [...SongFilter::normalize($filters), 'count' => $count, 'order' => $order]]);
            $draft->songs()->sync(collect($ids)->mapWithKeys(fn (int $id, int $index): array => [$id => ['position' => $index + 1]])->all());

            return $draft->refresh();
        });
    }

    /** "Set 2026-10-19", or "Set 2026-10-19 (2)" when that name is taken. */
    public static function defaultName(): string
    {
        $base = 'Set '.now()->format('Y-m-d');
        $taken = Playlist::query()->where('name', 'like', "{$base}%")->pluck('name')->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        $n = 2;

        while (in_array("{$base} ({$n})", $taken, true)) {
            $n++;
        }

        return "{$base} ({$n})";
    }
}
