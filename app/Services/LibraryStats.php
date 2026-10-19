<?php

namespace App\Services;

use App\Models\Song;

/** The figures over Biblioteca (SPEC §5.6) and Clasificar's progress (SPEC §5.4). */
final class LibraryStats
{
    /** @return array{total: int, withEnergy: int, withPercussion: int, missing: int} */
    public function summary(): array
    {
        return [
            'total' => Song::count(),
            'withEnergy' => Song::query()->whereNotNull('energy')->count(),
            'withPercussion' => Song::query()->whereHas('tags', fn ($tags) => $tags->where('category', 'percussion'))->count(),
            'missing' => Song::query()->where('missing', true)->count(),
        ];
    }
}
