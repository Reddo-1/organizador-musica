<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Services\Settings;
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
