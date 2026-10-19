<?php

namespace App\Support;

use Filament\Support\Colors\Color;

/**
 * The colours that carry data. SPEC §6: energy 1→5 is blue, green, yellow, orange, red everywhere
 * (tables, the song form and the energy curve); each set role and each tag category has its own colour.
 */
final class Palette
{
    /** @var array<int, string> */
    public const ENERGY = [1 => 'blue', 2 => 'green', 3 => 'yellow', 4 => 'orange', 5 => 'red'];

    /**
     * Tag categories take these in the order of config('music.tag_categories'), so a category added
     * to the config gets a colour without touching code.
     *
     * @var list<string>
     */
    public const TAG_CATEGORIES = ['teal', 'amber', 'rose', 'lime', 'sky', 'pink'];

    public static function energy(?int $energy): string
    {
        return self::ENERGY[$energy] ?? 'gray';
    }

    public static function tagCategory(string $category): string
    {
        $index = array_search($category, array_keys(config('music.tag_categories')), true);

        return $index === false
            ? 'gray'
            : self::TAG_CATEGORIES[$index % count(self::TAG_CATEGORIES)];
    }

    /**
     * Filament only renders badge colours it knows about, so every colour above is registered by name.
     *
     * @return array<string, array<int, string>>
     */
    public static function filamentColors(): array
    {
        return [
            'blue' => Color::Blue,
            'green' => Color::Green,
            'yellow' => Color::Yellow,
            'orange' => Color::Orange,
            'red' => Color::Red,
            'cyan' => Color::Cyan,
            'fuchsia' => Color::Fuchsia,
            'indigo' => Color::Indigo,
            'teal' => Color::Teal,
            'amber' => Color::Amber,
            'rose' => Color::Rose,
            'lime' => Color::Lime,
            'sky' => Color::Sky,
            'pink' => Color::Pink,
        ];
    }
}
