<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Library extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static ?string $navigationLabel = 'Biblioteca';

    protected static ?string $title = 'Biblioteca';

    protected static ?string $slug = 'biblioteca';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.library';
}
