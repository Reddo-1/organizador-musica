<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Scan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFolderOpen;

    protected static ?string $navigationLabel = 'Escanear';

    protected static ?string $title = 'Escanear';

    protected static ?string $slug = 'escanear';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.scan';
}
