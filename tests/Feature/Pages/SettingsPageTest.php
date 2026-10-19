<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Models\Song;
use App\Services\Settings;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

it('shows the current settings', function () {
    Livewire::test(SettingsPage::class)
        ->assertSchemaStateSet([
            'music_folder' => config('music.default_folder'),
            'default_genre' => 'Drum and Bass',
            'subfolder_as_subgenre' => true,
            'playlists_folder' => config('music.playlists_folder'),
        ]);
});

it('saves the settings', function () {
    $folder = temporaryFolder();

    Livewire::test(SettingsPage::class)
        ->fillForm([
            'music_folder' => $folder,
            'default_genre' => 'Neurofunk',
            'subfolder_as_subgenre' => false,
            'playlists_folder' => $folder.DIRECTORY_SEPARATOR.'Playlists',
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Ajustes guardados');

    $settings = app(Settings::class);

    expect($settings->musicFolder())->toBe(Settings::folder($folder))
        ->and($settings->defaultGenre())->toBe('Neurofunk')
        ->and($settings->subfolderAsSubgenre())->toBeFalse()
        ->and($settings->playlistsFolder())->toBe(Settings::folder($folder.DIRECTORY_SEPARATOR.'Playlists'));
});

it('warns when the music folder is not on this computer, but keeps it', function () {
    Livewire::test(SettingsPage::class)
        ->fillForm(['music_folder' => 'Q:\No\Existe'])
        ->call('save')
        ->assertNotified('La carpeta de música no existe en este equipo');

    expect(app(Settings::class)->musicFolder())->toBe('Q:\No\Existe');
});

it('requires the music folder', function () {
    Livewire::test(SettingsPage::class)
        ->fillForm(['music_folder' => ''])
        ->call('save')
        ->assertHasFormErrors(['music_folder' => 'required']);
});

it('hides the copy button when the database cannot be copied', function () {
    Livewire::test(SettingsPage::class)
        ->assertSee('Las copias automáticas solo funcionan con SQLite')
        ->assertDontSee('Copia ahora');
});

it('makes a copy when asked', function () {
    $this->app->useStoragePath($storage = temporaryFolder());
    $database = temporaryFolder().DIRECTORY_SEPARATOR.'music.sqlite';
    touch($database);
    config(['database.connections.file' => ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true]]);
    $this->artisan('migrate', ['--database' => 'file']);
    config(['database.default' => 'file']);

    try {
        Livewire::test(SettingsPage::class)
            ->assertSee('Aún no hay copias')
            ->callAction(TestAction::make('backupNow')->schemaComponent('backups-actions', schema: 'content'))
            ->assertNotified('Copia hecha')
            ->assertSee('music_'.now()->format('Y-m-d_H'));
    } finally {
        // RefreshDatabase rolls back the default connection when the test ends.
        config(['database.default' => 'sqlite']);
    }

    expect(File::glob($storage.'/app/backups/music_*.sqlite'))->toHaveCount(1);
});

it('moves the library to another folder', function () {
    $song = Song::factory()->create(['path' => 'D:\Musica\DNB\Neuro\Kanine - Sundown.mp3']);

    Livewire::test(SettingsPage::class)
        ->callAction(TestAction::make('relocateLibrary')->schemaComponent('relocate-actions', schema: 'content'), data: ['from' => 'D:\Musica\DNB', 'to' => 'E:\DNB'])
        ->assertNotified('1 tema con la ruta cambiada');

    expect($song->fresh()->path)->toBe('E:\DNB\Neuro\Kanine - Sundown.mp3');
});
