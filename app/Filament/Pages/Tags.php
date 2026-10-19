<?php

namespace App\Filament\Pages;

use App\Livewire\TagsTable;
use App\Models\Genre;
use App\Models\Tag;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\On;

/**
 * One tab per tag category from config('music.tag_categories'), plus genres (SPEC §5.9).
 * A category added to the config appears here without code changes.
 */
class Tags extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $navigationLabel = 'Etiquetas';

    protected static ?string $title = 'Etiquetas y géneros';

    protected static ?string $slug = 'etiquetas';

    protected static ?int $navigationSort = 6;

    /** A tag created, renamed or merged in one of the tables changes the counts on the tabs. */
    #[On('tags-changed')]
    public function refreshCounts(): void {}

    public function content(Schema $schema): Schema
    {
        $counts = Tag::query()->toBase()->selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category');

        $tabs = collect(config('music.tag_categories'))
            ->map(fn (string $label, string $category): Tab => Tab::make($label)
                ->id($category)
                ->badge((int) ($counts[$category] ?? 0))
                ->badgeColor('gray')
                ->schema([Livewire::make(TagsTable::class, ['category' => $category])->key("tags-{$category}")]))
            ->values()
            ->push(Tab::make('Géneros')
                ->id(TagsTable::GENRES)
                ->badge(Genre::count())
                ->badgeColor('gray')
                ->schema([Livewire::make(TagsTable::class, ['category' => TagsTable::GENRES])->key('tags-genres')]))
            ->all();

        return $schema->components([
            Tabs::make('Categorías')
                ->tabs($tabs)
                ->persistTabInQueryString('categoria')
                ->contained(false),
        ]);
    }
}
