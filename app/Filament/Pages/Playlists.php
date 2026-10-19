<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Playlists extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static ?string $navigationLabel = 'Playlists';

    protected static ?string $title = 'Playlists';

    protected static ?string $slug = 'playlists';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.playlists';
}
