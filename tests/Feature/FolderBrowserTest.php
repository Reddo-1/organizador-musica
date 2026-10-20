<?php

use App\Filament\Pages\Scan;
use App\Filament\Pages\Settings;
use App\Livewire\FolderPicker;
use App\Services\FolderBrowser;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

beforeEach(function () {
    $this->root = temporaryFolder();

    foreach (['Música/DNB/Neurofunk', 'Música/DNB/deep', 'Música/DNB/Ñu', 'Música/.cache', 'Música/$RECYCLE.BIN', 'Música/System Volume Information'] as $folder) {
        File::ensureDirectoryExists($this->root.'/'.$folder);
    }

    File::put($this->root.'/Música/DNB/tema.mp3', 'no es una carpeta');
});

it('lists only the visible subfolders, in natural order ignoring case', function () {
    $browser = app(FolderBrowser::class);

    expect(array_column($browser->children($this->root.'/Música/DNB'), 'name'))->toBe(['deep', 'Neurofunk', 'Ñu'])
        ->and(array_column($browser->children($this->root.'/Música'), 'name'))->toBe(['DNB'])
        ->and($browser->children($this->root.'/Música/DNB')[0]['path'])->toBe($this->root.'/Música/DNB/deep')
        ->and($browser->children($this->root.'/no-existe'))->toBe([]);
});

it('goes up to the drive and stops there', function () {
    $browser = app(FolderBrowser::class);

    expect($browser->parent('C:\Users\Ricardo\Music'))->toBe('C:\Users\Ricardo')
        ->and($browser->parent('C:\Users'))->toBe('C:\\')
        ->and($browser->parent('C:\\'))->toBeNull()
        ->and($browser->parent('/home/ricardo'))->toBe('/home')
        ->and($browser->parent('/home'))->toBe('/')
        ->and($browser->parent('/'))->toBeNull()
        ->and($browser->isRoot('D:'))->toBeTrue();
});

it('opens on the nearest folder that exists, moves around and hands back the chosen one', function () {
    Livewire::test(FolderPicker::class, ['target' => 'data.music_folder', 'start' => $this->root.'/Música/DNB/Borrada'])
        ->assertSet('path', $this->root.'/Música/DNB')
        ->assertSee('Neurofunk')
        ->assertDontSee('tema.mp3')
        ->call('open', $this->root.'/Música/DNB/Neurofunk')
        ->assertSet('path', $this->root.'/Música/DNB/Neurofunk')
        ->assertSee('Esta carpeta no tiene subcarpetas.')
        ->call('open', $this->root.'/no-existe')
        ->assertSet('path', $this->root.'/Música/DNB/Neurofunk')
        ->call('up')
        ->assertSet('path', $this->root.'/Música/DNB')
        ->call('choose')
        ->assertDispatched('folder-picked', target: 'data.music_folder', path: $this->root.'/Música/DNB');
});

it('fills the field the picker was opened for, and only the fields the page allows', function () {
    Livewire::test(Scan::class)
        ->call('folderPicked', 'data.music_folder', 'E:\DNB')
        ->assertSet('data.music_folder', 'E:\DNB')
        ->call('folderPicked', 'data.default_genre', 'E:\DNB')
        ->assertSet('data.default_genre', fn ($genre) => $genre !== 'E:\DNB');

    Livewire::test(Settings::class)
        ->call('folderPicked', 'data.playlists_folder', 'E:\Playlists')
        ->assertSet('data.playlists_folder', 'E:\Playlists');
});
