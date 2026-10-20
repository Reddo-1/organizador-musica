<?php

namespace App\Livewire;

use App\Services\FolderBrowser;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The folder browser inside the «Elegir carpeta» modal: opens on the folder already written in the
 * field (or the nearest one that exists), goes into subfolders and up, and hands the chosen path
 * back to the page as the "folder-picked" event.
 */
class FolderPicker extends Component
{
    /** The state path of the field to fill, like "data.music_folder". */
    #[Locked]
    public string $target;

    /** The folder on screen; null lists the drives. */
    public ?string $path = null;

    public function mount(string $target, ?string $start, FolderBrowser $browser): void
    {
        $this->target = $target;
        $start = trim((string) $start);

        while ($start !== '' && ! $browser->exists($start)) {
            $start = (string) $browser->parent($start);
        }

        $this->path = $start !== '' ? $start : null;
    }

    public function open(string $path, FolderBrowser $browser): void
    {
        if ($browser->exists($path)) {
            $this->path = $path;
        }
    }

    public function up(FolderBrowser $browser): void
    {
        $this->path = $this->path === null ? null : $browser->parent($this->path);
    }

    public function drives(): void
    {
        $this->path = null;
    }

    public function choose(): void
    {
        if ($this->path !== null) {
            $this->dispatch('folder-picked', target: $this->target, path: $this->path);
        }
    }

    public function render(FolderBrowser $browser): View
    {
        return view('livewire.folder-picker', [
            'folders' => $this->path === null ? $browser->roots() : $browser->children($this->path),
            'hasParent' => $this->path !== null,
        ]);
    }
}
