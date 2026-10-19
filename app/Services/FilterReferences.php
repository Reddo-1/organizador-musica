<?php

namespace App\Services;

use App\Models\FilterPreset;
use App\Models\Playlist;

/**
 * Saved filters (presets, and the filters a playlist was generated with) point at tags and
 * genres by id. When a tag or genre is merged or deleted, those references follow it.
 * Filters look like ['genres' => [ids], 'tags' => [category => ['include' => [ids], 'exclude' => [ids]]]].
 */
final class FilterReferences
{
    public function replaceTag(int $from, int $to): void
    {
        $this->rewrite(fn (array $filters): array => $this->mapTagIds($filters, fn (int $id): ?int => $id === $from ? $to : $id));
    }

    public function forgetTag(int $id): void
    {
        $this->rewrite(fn (array $filters): array => $this->mapTagIds($filters, fn (int $tagId): ?int => $tagId === $id ? null : $tagId));
    }

    public function replaceGenre(int $from, int $to): void
    {
        $this->rewrite(fn (array $filters): array => $this->mapGenreIds($filters, fn (int $id): ?int => $id === $from ? $to : $id));
    }

    public function forgetGenre(int $id): void
    {
        $this->rewrite(fn (array $filters): array => $this->mapGenreIds($filters, fn (int $genreId): ?int => $genreId === $id ? null : $genreId));
    }

    /** @param  callable(array<string, mixed>): array<string, mixed>  $change */
    private function rewrite(callable $change): void
    {
        foreach ([FilterPreset::query(), Playlist::query()->whereNotNull('filters')] as $query) {
            $query->each(function (FilterPreset|Playlist $model) use ($change): void {
                $filters = $model->filters ?? [];
                $changed = $change($filters);

                if ($changed !== $filters) {
                    $model->update(['filters' => $changed]);
                }
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  callable(int): ?int  $map
     * @return array<string, mixed>
     */
    private function mapTagIds(array $filters, callable $map): array
    {
        foreach ($filters['tags'] ?? [] as $category => $rule) {
            foreach (['include', 'exclude'] as $part) {
                if (isset($rule[$part]) && is_array($rule[$part])) {
                    $filters['tags'][$category][$part] = self::mapIds($rule[$part], $map);
                }
            }
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  callable(int): ?int  $map
     * @return array<string, mixed>
     */
    private function mapGenreIds(array $filters, callable $map): array
    {
        if (isset($filters['genres']) && is_array($filters['genres'])) {
            $filters['genres'] = self::mapIds($filters['genres'], $map);
        }

        return $filters;
    }

    /**
     * @param  array<int|string>  $ids
     * @param  callable(int): ?int  $map
     * @return list<int>
     */
    private static function mapIds(array $ids, callable $map): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn (int|string $id): ?int => $map((int) $id), $ids),
            fn (?int $id): bool => $id !== null,
        )));
    }
}
