<?php

namespace App\Filament\Resources\Songs\Pages;

use App\Filament\Resources\Songs\SongResource;
use App\Models\Song;
use App\Services\SongEditor;
use App\Support\PathHtml;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

/** The full song card (SPEC §5.6), the same form as Clasificar. */
class EditSong extends EditRecord
{
    protected static string $resource = SongResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var Song $song */
        $song = $this->getRecord();

        return $song->displayName();
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Song $song */
        $song = $this->getRecord();

        return app(SongEditor::class)->formData($song);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Song $record */
        return app(SongEditor::class)->save($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('remove')
                ->label('Quitar de la app')
                ->icon(Heroicon::OutlinedTrash)
                ->color('gray')
                ->requiresConfirmation()
                ->modalIconColor('danger')
                ->modalHeading('¿Quitar este tema de la app?')
                ->modalDescription(fn (): Htmlable => PathHtml::render($this->getRecord()->path, 'app-path-block'))
                ->modalSubmitActionLabel('Quitar')
                ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                ->action(function (SongEditor $editor): void {
                    $editor->remove($this->getRecord()->getKey());

                    Notification::make()->title('Tema quitado de la app. El archivo sigue donde estaba.')->success()->send();

                    $this->redirect(SongResource::getUrl('index'));
                }),
        ];
    }
}
