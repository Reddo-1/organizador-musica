<?php

namespace Database\Factories;

use App\Models\FilterPreset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FilterPreset>
 */
class FilterPresetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'filters' => [],
        ];
    }
}
