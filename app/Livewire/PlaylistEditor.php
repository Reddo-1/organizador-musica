<?php

namespace App\Livewire;

use App\Models\Playlist;
use App\Services\M3u8Exporter;
use App\Services\PlaylistEditor as Editor;
use App\Support\Duration;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A generated playlist (SPEC §5.7): its name, its energy curve, its songs to drag into place or
 * take out, and the .m3u8 export for rekordbox. Used by Generar playlist and by the history.
 */
class PlaylistEditor extends Component
{
    #[Locked]
    public int $playlistId;

    /** Shown on Generar playlist while the playlist is still a draft that "Generar" refills. */
    #[Locked]
    public bool $draft = false;

    public string $name = '';

    public ?string $savedPath = null;

    public function mount(int $playlistId, bool $draft = false): void
    {
        $this->playlistId = $playlistId;
        $this->draft = $draft;
        $this->name = $this->playlist()->name;
    }

    public function updatedName(Editor $editor): void
    {
        try {
            $editor->rename($this->playlist(), $this->name);
        } catch (InvalidArgumentException $exception) {
            $this->name = $this->playlist()->name;
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }

    public function removeSong(int $songId, Editor $editor): void
    {
        $editor->remove($this->playlist(), $songId);
    }

    /** wire:sort calls this with the song and its new place (0 = first). */
    public function moveSong(int|string $songId, int $position, Editor $editor): void
    {
        $editor->move($this->playlist(), (int) $songId, $position);
    }

    public function save(Editor $editor): void
    {
        try {
            $this->savedPath = $editor->export($this->playlist());
        } catch (\RuntimeException $exception) {
            Notification::make()->title('No se pudo guardar el .m3u8')->body($exception->getMessage())->danger()->persistent()->send();

            return;
        }

        Notification::make()->title('Playlist guardada')->body($this->savedPath)->success()->send();
        $this->exported();
    }

    public function download(Editor $editor): StreamedResponse
    {
        $playlist = $this->playlist();
        $content = $editor->m3u8($playlist);

        $this->exported();

        return response()->streamDownload(
            function () use ($content): void {
                echo $content;
            },
            M3u8Exporter::fileName($playlist->name),
            ['Content-Type' => 'audio/x-mpegurl; charset=UTF-8'],
        );
    }

    public function render(Editor $editor): View
    {
        $playlist = $this->playlist();
        $songs = $playlist->songs()->with('genre')->get();
        $duration = $editor->duration($playlist);

        return view('livewire.playlist-editor', [
            'playlist' => $playlist,
            'songs' => $songs,
            'total' => Duration::human($duration['seconds']),
            'unknownDurations' => $duration['unknown'],
        ]);
    }

    /** Once exported, the draft is kept: Generar playlist starts a new one next time. */
    private function exported(): void
    {
        $this->draft = false;
        $this->dispatch('playlist-exported', id: $this->playlistId);
    }

    private function playlist(): Playlist
    {
        return Playlist::query()->findOrFail($this->playlistId);
    }
}
