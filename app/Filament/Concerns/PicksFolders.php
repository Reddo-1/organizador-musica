<?php

namespace App\Filament\Concerns;

use App\Livewire\FolderPicker;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;

/**
 * A folder button next to a path field: it opens the FolderPicker and writes the chosen folder in
 * the field. The page lists in pickableFolders() the fields it may fill.
 */
trait PicksFolders
{
    /** @return list<string> State paths like "data.music_folder". */
    abstract protected function pickableFolders(): array;

    public static function pickFolderAction(string $target): Action
    {
        return Action::make('pickFolder')
            ->label('Elegir carpeta')
            ->icon(Heroicon::OutlinedFolderOpen)
            ->tooltip('Elegir la carpeta en el disco')
            ->modalHeading('Elegir carpeta')
            ->modalWidth('2xl')
            ->modalContent(fn (?string $state): View => view('filament.folder-picker', [
                'picker' => FolderPicker::class,
                'start' => $state,
                'target' => $target,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cancelar');
    }

    #[On('folder-picked')]
    public function folderPicked(string $target, string $path): void
    {
        if (! in_array($target, $this->pickableFolders(), true)) {
            return;
        }

        data_set($this, $target, $path);
        $this->unmountAction();
    }
}
