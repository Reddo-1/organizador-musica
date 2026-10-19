<?php

namespace Database\Factories;

use App\Models\Playlist;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Playlist>
 */
class PlaylistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Set '.fake()->date(),
            'filters' => null,
        ];
    }
}
