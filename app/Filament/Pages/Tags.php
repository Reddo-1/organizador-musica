<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Tags extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Etiquetas';

    protected static ?string $title = 'Etiquetas';

    protected static ?string $slug = 'etiquetas';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.tags';
}
