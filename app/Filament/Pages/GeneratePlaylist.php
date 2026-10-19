<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Support\Icons\Heroicon;

class GeneratePlaylist extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'Generar playlist';

    protected static ?string $title = 'Generar playlist';

    protected static ?string $slug = 'generar';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.generate-playlist';

    /** The home page of the app (SPEC §6), served at the panel root like Filament's dashboard. */
    public static function getRoutePath(Panel $panel): string
    {
        return '/';
    }
}
