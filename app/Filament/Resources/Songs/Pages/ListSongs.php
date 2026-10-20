<?php

namespace App\Filament\Resources\Songs\Pages;

use App\Enums\SetRole;
use App\Filament\Resources\Songs\SongResource;
use App\Models\Song;
use App\Services\LibraryStats;
use App\Services\SongEditor;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class ListSongs extends ListRecords
{
    protected static string $resource = SongResource::class;

    protected static ?string $title = 'Biblioteca';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.songs.metrics')
                ->viewData(fn (LibraryStats $stats): array => ['stats' => $stats->summary()]),
            EmbeddedTable::make(),
            View::make('filament.songs.dock'),
        ]);
    }

    /** Inline energy from the row's meter (SPEC §5.6). Clicking the current value clears it. */
    public function setEnergy(int $song, ?int $energy, SongEditor $editor): void
    {
        if ($energy !== null && ($energy < 1 || $energy > 5)) {
            return;
        }

        $editor->setEnergy(Song::query()->findOrFail($song)->id, $energy);
    }

    /** Inline set role from the row's select. */
    public function setRole(int $song, ?string $role, SongEditor $editor): void
    {
        if (filled($role) && SetRole::tryFrom($role) === null) {
            return;
        }

        $editor->setRole(Song::query()->findOrFail($song)->id, $role);
    }
}
