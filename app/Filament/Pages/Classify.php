<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Classify extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Clasificar';

    protected static ?string $title = 'Clasificar';

    protected static ?string $slug = 'clasificar';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.classify';
}
