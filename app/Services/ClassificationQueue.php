<?php

namespace App\Services;

use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

/**
 * The songs Clasificar works through (SPEC §5.4): one view at a time (no energy, no percussion,
 * no role, or all of them), narrowed by a search on artist, title or path, in library order.
 */
final class ClassificationQueue
{
    public const NO_ENERGY = 'sin-energia';

    public const NO_PERCUSSION = 'sin-percusion';

    public const NO_ROLE = 'sin-rol';

    public const ALL = 'todas';

    /** @var array<string, string> */
    public const VIEWS = [
        self::NO_ENERGY => 'Sin energía',
        self::NO_PERCUSSION => 'Sin percusión',
        self::NO_ROLE => 'Sin rol',
        self::ALL => 'Todas',
    ];

    /** @return Builder<Song> */
    public function query(string $view, string $search = ''): Builder
    {
        $query = Song::query()
            ->orderBy('artist')
            ->orderBy('title')
            ->orderBy('id');

        match ($view) {
            self::NO_ENERGY => $query->whereNull('energy'),
            self::NO_PERCUSSION => $query->whereDoesntHave('tags', fn (Builder $tags) => $tags->where('category', 'percussion')),
            self::NO_ROLE => $query->whereNull('set_role'),
            self::ALL => null,
            default => throw new InvalidArgumentException("La vista {$view} no existe."),
        };

        $search = trim($search);

        if ($search !== '') {
            $query->where(fn (Builder $query) => $query
                ->where('artist', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('path', 'like', "%{$search}%"));
        }

        return $query;
    }

    /** @return list<int> The songs of the view, in order. */
    public function ids(string $view, string $search = ''): array
    {
        return $this->query($view, $search)->pluck('id')->all();
    }

    /** @return array<string, int> How many songs each view holds, with the same search. */
    public function counts(string $search = ''): array
    {
        return collect(self::VIEWS)
            ->map(fn (string $label, string $view): int => $this->query($view, $search)->count())
            ->all();
    }

    /** @return array{done: int, total: int} Songs with energy out of all songs (the progress bar). */
    public function progress(): array
    {
        return [
            'done' => Song::query()->whereNotNull('energy')->count(),
            'total' => Song::query()->count(),
        ];
    }

    /**
     * The song after $current in a snapshot of the queue. Songs stay in the snapshot after being
     * classified, so "save and next" walks the queue in order even though the song just saved
     * no longer belongs to the view.
     *
     * @param  list<int>  $queue
     */
    public static function after(array $queue, ?int $current): ?int
    {
        $index = $current === null ? false : array_search($current, $queue, true);

        if ($index === false) {
            return $queue[0] ?? null;
        }

        return $queue[$index + 1] ?? null;
    }

    /** @param  list<int>  $queue */
    public static function before(array $queue, ?int $current): ?int
    {
        $index = $current === null ? false : array_search($current, $queue, true);

        return $index === false || $index === 0 ? null : $queue[$index - 1];
    }
}
