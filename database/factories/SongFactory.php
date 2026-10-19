<?php

namespace Database\Factories;

use App\Enums\SetRole;
use App\Models\Song;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Song>
 */
class SongFactory extends Factory
{
    public function definition(): array
    {
        $artist = fake()->randomElement([
            'Phibes', 'Kanine', 'Rova', 'MaySev', 'Gladde Paling', 'Frannabik', 'Ekko & Sidetrack', 'LSB',
        ]);
        $title = ucfirst(fake()->unique()->words(2, true));

        return [
            'path' => "C:\\Users\\Ricardo\\Music\\Musikote\\DNB\\{$artist}\\{$artist} - {$title}.mp3",
            'title' => $title,
            'artist' => $artist,
            'song_year' => fake()->numberBetween(2008, 2026),
            'discovered_year' => fake()->numberBetween(2016, 2026),
            'bpm' => fake()->randomElement([170, 172, 173, 174, 175, 176]),
            'musical_key' => fake()->numberBetween(1, 12).fake()->randomElement(['A', 'B']),
            'duration' => fake()->numberBetween(240, 420),
            'missing' => false,
        ];
    }

    public function classified(?int $energy = null, ?SetRole $role = null): static
    {
        return $this->state(fn (): array => [
            'energy' => $energy ?? fake()->numberBetween(1, 5),
            'set_role' => $role ?? fake()->randomElement(SetRole::cases()),
        ]);
    }

    public function missing(): static
    {
        return $this->state(['missing' => true]);
    }
}
