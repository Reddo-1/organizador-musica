<?php

use App\Filament\Resources\Playlists\Pages\ListPlaylists;
use App\Models\Playlist;
use App\Models\Song;
use App\Services\Settings;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lists the generated playlists, newest first', function () {
    $old = Playlist::factory()->create(['name' => 'Viejo', 'updated_at' => now()->subWeek()]);
    $new = Playlist::factory()->create(['name' => 'Nuevo']);

    Livewire::test(ListPlaylists::class)->assertCanSeeTableRecords([$new, $old], inOrder: true);
});

it('exports again from the history and deletes', function () {
    $folder = temporaryFolder();
    app(Settings::class)->setPlaylistsFolder($folder);
    $playlist = Playlist::factory()->create(['name' => 'Set del sábado']);
    $playlist->songs()->attach(Song::factory()->create()->id, ['position' => 1]);

    Livewire::test(ListPlaylists::class)
        ->callAction(TestAction::make('save')->table($playlist))
        ->assertNotified('Playlist guardada')
        ->callAction(TestAction::make('delete')->table($playlist))
        ->assertNotified('Playlist borrada');

    expect(file_exists($folder.DIRECTORY_SEPARATOR.'Set del sábado.m3u8'))->toBeTrue()
        ->and(Playlist::count())->toBe(0)
        ->and(Song::count())->toBe(1);
});

it('opens a playlist in the editor', function () {
    $playlist = Playlist::factory()->create(['name' => 'Para abrir']);

    $this->get("/playlists/{$playlist->id}")->assertOk()->assertSee('Para abrir', escape: false);
});
