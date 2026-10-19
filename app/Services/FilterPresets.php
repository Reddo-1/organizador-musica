<?php

namespace App\Services;

use App\Models\FilterPreset;
use App\Support\NameKey;
use InvalidArgumentException;

/** Named states of the Generar playlist filters (SPEC §5.7). */
final class FilterPresets
{
    /**
     * Saves the filters under a name; a preset with the same name (ignoring case) is replaced.
     *
     * @param  array<string, mixed>  $filters
     */
    public function save(string $name, array $filters): FilterPreset
    {
        $name = NameKey::clean($name);

        if ($name === '') {
            throw new InvalidArgumentException('El preset necesita un nombre.');
        }

        $existing = FilterPreset::query()->get()->first(fn (FilterPreset $preset): bool => NameKey::of($preset->name) === NameKey::of($name));
        $data = ['name' => $name, 'filters' => $filters];

        if ($existing) {
            $existing->update($data);

            return $existing;
        }

        return FilterPreset::create($data);
    }

    /** @return array<string, mixed> */
    public function load(int $id): array
    {
        return FilterPreset::query()->findOrFail($id)->filters ?? [];
    }

    public function delete(int $id): void
    {
        FilterPreset::query()->whereKey($id)->delete();
    }

    /** @return array<int, string> */
    public function options(): array
    {
        return FilterPreset::query()->orderBy('name')->pluck('name', 'id')->all();
    }
}
