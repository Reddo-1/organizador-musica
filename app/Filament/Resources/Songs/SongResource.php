<?php

namespace App\Filament\Resources\Songs;

use App\Filament\Resources\Songs\Pages\EditSong;
use App\Filament\Resources\Songs\Pages\ListSongs;
use App\Filament\Resources\Songs\Schemas\SongForm;
use App\Filament\Resources\Songs\Tables\SongsTable;
use App\Models\Song;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Biblioteca (SPEC §5.6). Songs only come in by scanning, so there is no create page. */
class SongResource extends Resource
{
    protected static ?string $model = Song::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMusicalNote;

    protected static ?string $navigationLabel = 'Biblioteca';

    protected static ?string $modelLabel = 'tema';

    protected static ?string $pluralModelLabel = 'temas';

    protected static ?string $slug = 'biblioteca';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return SongForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SongsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSongs::route('/'),
            'edit' => EditSong::route('/{record}/edit'),
        ];
    }

    /** @return list<string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'artist'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        /** @var Song $record */
        return $record->displayName();
    }
}
