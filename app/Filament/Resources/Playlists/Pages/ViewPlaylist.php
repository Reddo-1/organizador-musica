<?php

namespace App\Filament\Resources\Playlists\Pages;

use App\Filament\Resources\Playlists\PlaylistResource;
use App\Livewire\PlaylistEditor;
use App\Models\Playlist;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/** A playlist from the history, in the same editor as Generar playlist. */
class ViewPlaylist extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PlaylistResource::class;

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Playlist';
    }

    public function content(Schema $schema): Schema
    {
        /** @var Playlist $playlist */
        $playlist = $this->getRecord();

        return $schema->components([
            Livewire::make(PlaylistEditor::class, ['playlistId' => $playlist->id])->key("editor-{$playlist->id}"),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('delete')
                ->label('Borrar')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->requiresConfirmation()
                ->modalIconColor('danger')
                ->modalHeading('¿Borrar esta playlist?')
                ->modalDescription('Se borra de la app. Los temas y los .m3u8 que hayas guardado no se tocan.')
                ->modalSubmitActionLabel('Borrar')
                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                ->action(function (): void {
                    $this->getRecord()->delete();

                    Notification::make()->title('Playlist borrada')->success()->send();

                    $this->redirect(PlaylistResource::getUrl('index'));
                }),
        ];
    }
}
