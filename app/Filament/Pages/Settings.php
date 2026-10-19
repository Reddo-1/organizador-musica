<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Ajustes';

    protected static ?string $title = 'Ajustes';

    protected static ?string $slug = 'ajustes';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.settings';
}
