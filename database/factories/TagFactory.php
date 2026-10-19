<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'category' => Tag::SUBGENRE,
            'name' => ucfirst(fake()->unique()->word()),
        ];
    }

    public function category(string $category): static
    {
        return $this->state(['category' => $category]);
    }
}
