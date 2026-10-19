<?php

namespace App\Services;

use App\Enums\SetRole;
use App\Models\Song;
use Illuminate\Database\Eloquent\Builder;

/**
 * The filters of Generar playlist (SPEC §5.7), as a query. Filters are stored as:
 *
 *   genres          list of genre ids (any of them)
 *   roles           list of set roles (any of them)
 *   tags            [category => [include => ids, mode => any|all, exclude => ids]]
 *   energy, song_year, discovered_year, bpm   [min, max]
 *   include_missing bool
 *
 * A range that spans the whole library from its lowest to its highest value does not filter, so
 * songs without that value still come in.
 */
final class SongFilter
{
    /** @var list<string> */
    public const RANGES = ['energy', 'song_year', 'discovered_year', 'bpm'];

    public const ANY = 'any';

    public const ALL = 'all';

    /** @param  array<string, mixed>  $filters */
    public function apply(array $filters, ?Builder $query = null): Builder
    {
        $query ??= Song::query();
        $filters = self::normalize($filters);

        if ($filters['genres'] !== []) {
            $query->whereIn('genre_id', $filters['genres']);
        }

        if ($filters['roles'] !== []) {
            $query->whereIn('set_role', $filters['roles']);
        }

        foreach ($filters['tags'] as $category => $rule) {
            if ($rule['include'] !== []) {
                $tagged = fn (Builder $tags) => $tags->where('category', $category)->whereIn('tags.id', $rule['include']);

                $rule['mode'] === self::ALL
                    ? $query->whereHas('tags', $tagged, '=', count($rule['include']))
                    : $query->whereHas('tags', $tagged);
            }

            if ($rule['exclude'] !== []) {
                $query->whereDoesntHave('tags', fn (Builder $tags) => $tags->where('category', $category)->whereIn('tags.id', $rule['exclude']));
            }
        }

        $bounds = $this->bounds();

        foreach (self::RANGES as $field) {
            $range = $filters[$field];

            if ($range !== null && ! self::spans($range, $bounds[$field])) {
                $query->whereBetween($field, $range);
            }
        }

        if (! $filters['include_missing']) {
            $query->where('missing', false);
        }

        return $query;
    }

    /**
     * The lowest and highest value of each range field in the library; null when no song has one.
     *
     * @return array<string, array{0: float|int, 1: float|int}|null>
     */
    public function bounds(): array
    {
        $row = Song::query()->toBase()->selectRaw(collect(self::RANGES)
            ->map(fn (string $field): string => "min({$field}) as {$field}_min, max({$field}) as {$field}_max")
            ->implode(', '))->first();

        return collect(self::RANGES)->mapWithKeys(function (string $field) use ($row): array {
            $min = $row->{"{$field}_min"};
            $max = $row->{"{$field}_max"};
            $cast = fn ($value) => $field === 'bpm' ? (float) $value : (int) $value;

            return [$field => $min === null ? null : [$cast($min), $cast($max)]];
        })->all();
    }

    /**
     * @param  array{0: float|int, 1: float|int}  $range
     * @param  array{0: float|int, 1: float|int}|null  $bounds
     */
    public static function spans(array $range, ?array $bounds): bool
    {
        return $bounds === null || ($range[0] <= $bounds[0] && $range[1] >= $bounds[1]);
    }

    /**
     * Filters with every key present and of the right type, whatever was stored.
     *
     * @param  array<string, mixed>  $filters
     * @return array{genres: list<int>, roles: list<string>, tags: array<string, array{include: list<int>, mode: string, exclude: list<int>}>, energy: ?array, song_year: ?array, discovered_year: ?array, bpm: ?array, include_missing: bool}
     */
    public static function normalize(array $filters): array
    {
        $ids = fn ($values): array => array_values(array_unique(array_map('intval', array_filter((array) $values, 'is_numeric'))));
        $roles = array_column(SetRole::cases(), 'value');

        $normalized = [
            'genres' => $ids($filters['genres'] ?? []),
            'roles' => array_values(array_intersect((array) ($filters['roles'] ?? []), $roles)),
            'tags' => [],
            'include_missing' => (bool) ($filters['include_missing'] ?? false),
        ];

        foreach (array_keys(config('music.tag_categories')) as $category) {
            $rule = $filters['tags'][$category] ?? [];
            $normalized['tags'][$category] = [
                'include' => $ids($rule['include'] ?? []),
                'mode' => ($rule['mode'] ?? self::ANY) === self::ALL ? self::ALL : self::ANY,
                'exclude' => $ids($rule['exclude'] ?? []),
            ];
        }

        foreach (self::RANGES as $field) {
            $range = array_values((array) ($filters[$field] ?? []));
            $normalized[$field] = count($range) === 2 && is_numeric($range[0]) && is_numeric($range[1])
                ? self::sortedRange($range, $field)
                : null;
        }

        return $normalized;
    }

    /** @return array{0: float|int, 1: float|int} */
    private static function sortedRange(array $range, string $field): array
    {
        $cast = fn ($value) => $field === 'bpm' ? (float) $value : (int) round((float) $value);
        $values = [$cast($range[0]), $cast($range[1])];
        sort($values);

        return $values;
    }
}
