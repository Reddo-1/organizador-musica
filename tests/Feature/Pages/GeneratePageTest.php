<?php

use App\Filament\Pages\GeneratePlaylist;
use App\Livewire\PlaylistEditor;
use App\Models\FilterPreset;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Settings;
use Livewire\Livewire;

beforeEach(function () {
    $this->neuro = Tag::findOrCreateNamed('subgenre', 'Neurofunk');
    Song::factory()->count(30)->create()->each(fn (Song $song, int $i) => $song->update(['energy' => ($i % 5) + 1]));
    Song::query()->limit(10)->get()->each(fn (Song $song) => $song->tags()->attach($this->neuro));
});

it('is the home page and counts the songs that match, live', function () {
    $this->get('/')->assertOk()->assertSee('temas cumplen los filtros');

    Livewire::test(GeneratePlaylist::class)
        ->assertSeeInOrder(['30', 'de', '30'])
        ->set('data.tags.subgenre.include', [$this->neuro->id])
        ->assertSeeInOrder(['10', 'de', '30'])
        ->set('data.energy', [5, 5])
        ->assertSeeInOrder(['2', 'de', '30']);
});

it('generates a draft and fills the same draft again until it is exported', function () {
    $page = Livewire::test(GeneratePlaylist::class)
        ->fillForm(['count' => 20, 'order' => 'energy_asc'])
        ->call('generate');

    $draft = Playlist::sole();

    expect($draft->songs()->count())->toBe(20)
        ->and($draft->songs()->pluck('energy')->all())->toBe(collect($draft->songs()->pluck('energy'))->sort()->values()->all());

    $page->call('generate');

    expect(Playlist::count())->toBe(1);

    $page->dispatch('playlist-exported', id: $draft->id)->call('generate');

    expect(Playlist::count())->toBe(2);
});

it('starts a new playlist on request', function () {
    Livewire::test(GeneratePlaylist::class)
        ->call('generate')
        ->call('startNewPlaylist')
        ->assertNotified('El borrador queda guardado en Playlists')
        ->call('generate');

    expect(Playlist::count())->toBe(2);
});

it('warns when no song matches the filters', function () {
    Livewire::test(GeneratePlaylist::class)
        ->set('data.roles', ['open'])
        ->call('generate')
        ->assertNotified('Ningún tema cumple los filtros');

    expect(Playlist::count())->toBe(0);
});

it('saves, loads and deletes presets', function () {
    $page = Livewire::test(GeneratePlaylist::class)
        ->set('data.energy', [4, 5])
        ->set('data.tags.subgenre.include', [$this->neuro->id])
        ->callAction('savePreset', data: ['name' => 'Pico neuro'])
        ->assertNotified('Preset guardado');

    $preset = FilterPreset::sole();

    expect($preset->filters['energy'])->toBe([4, 5])
        ->and($preset->filters['tags']['subgenre']['include'])->toBe([$this->neuro->id]);

    $page->set('data.energy', [1, 5])
        ->set('data.tags.subgenre.include', [])
        ->set('preset', $preset->id)
        ->assertSet('data.energy', [4, 5])
        ->assertSet('data.tags.subgenre.include', [$this->neuro->id])
        ->callAction('deletePreset')
        ->assertNotified('Preset borrado');

    expect(FilterPreset::count())->toBe(0);
});

it('edits the playlist: remove, drag, rename, save and download', function () {
    $folder = temporaryFolder();
    app(Settings::class)->setPlaylistsFolder($folder);
    $playlist = Playlist::factory()->create(['name' => 'Set 2026-10-19']);
    $songs = Song::query()->limit(4)->get();
    $playlist->songs()->attach($songs->mapWithKeys(fn (Song $song, int $i) => [$song->id => ['position' => $i + 1]])->all());

    $editor = Livewire::test(PlaylistEditor::class, ['playlistId' => $playlist->id, 'draft' => true])
        ->call('removeSong', $songs[1]->id)
        ->call('moveSong', (string) $songs[3]->id, 0)
        ->set('name', 'Cierre techstep');

    expect($playlist->fresh()->name)->toBe('Cierre techstep')
        ->and($playlist->songs()->pluck('songs.id')->all())->toBe([$songs[3]->id, $songs[0]->id, $songs[2]->id]);

    $editor->call('save')
        ->assertDispatched('playlist-exported', id: $playlist->id)
        ->assertSet('draft', false)
        ->assertSee('Guardada en');

    expect(file_exists($folder.DIRECTORY_SEPARATOR.'Cierre techstep.m3u8'))->toBeTrue();

    $editor->call('download')->assertFileDownloaded('Cierre techstep.m3u8');
});

it('keeps the name when it is emptied', function () {
    $playlist = Playlist::factory()->create(['name' => 'Set']);

    Livewire::test(PlaylistEditor::class, ['playlistId' => $playlist->id])
        ->set('name', '  ')
        ->assertSet('name', 'Set')
        ->assertNotified('La playlist necesita un nombre.');
});
