<?php

use App\Models\Playlist;
use App\Models\Song;
use App\Services\PlaylistEditor;
use App\Services\Settings;

beforeEach(function () {
    $this->editor = app(PlaylistEditor::class);
    $this->playlist = Playlist::factory()->create(['name' => 'Set de prueba']);
    $this->songs = collect([[3, 300], [5, 245.4], [null, null], [2, 200]])
        ->map(fn (array $s) => Song::factory()->create(['energy' => $s[0], 'duration' => $s[1]]));
    $this->playlist->songs()->attach($this->songs->mapWithKeys(fn (Song $song, int $i) => [$song->id => ['position' => $i + 1]])->all());
});

it('takes a song out and closes the gap', function () {
    $this->editor->remove($this->playlist, $this->songs[1]->id);

    expect($this->playlist->songs()->pluck('songs.id')->all())->toBe([$this->songs[0]->id, $this->songs[2]->id, $this->songs[3]->id])
        ->and($this->playlist->songs()->pluck('playlist_song.position')->all())->toBe([1, 2, 3]);
});

it('moves a song to another place, as when dragging', function () {
    $this->editor->move($this->playlist, $this->songs[3]->id, 0);

    expect($this->playlist->songs()->pluck('songs.id')->all())->toBe([$this->songs[3]->id, $this->songs[0]->id, $this->songs[1]->id, $this->songs[2]->id]);

    $this->editor->move($this->playlist, $this->songs[3]->id, 9);

    expect($this->playlist->songs()->pluck('songs.id')->last())->toBe($this->songs[3]->id);
});

it('adds up the duration and counts songs without one', function () {
    expect($this->editor->duration($this->playlist))->toBe(['seconds' => 745.4, 'unknown' => 1]);
});

it('gives the energy curve by position', function () {
    expect($this->editor->energyCurve($this->playlist))->toBe([3, 5, null, 2]);
});

it('renames and refuses an empty name', function () {
    $this->editor->rename($this->playlist, '  Cierre   techstep ');

    expect($this->playlist->fresh()->name)->toBe('Cierre techstep');

    $this->editor->rename($this->playlist, ' ');
})->throws(InvalidArgumentException::class);

it('exports the playlist in order to the playlists folder', function () {
    $folder = temporaryFolder();
    app(Settings::class)->setPlaylistsFolder($folder);

    $path = $this->editor->export($this->playlist);

    expect($path)->toEndWith('Set de prueba.m3u8')
        ->and(file_get_contents($path))->toStartWith("#EXTM3U\r\n#EXTINF:300,")
        ->and(substr_count(file_get_contents($path), '#EXTINF'))->toBe(4);
});
