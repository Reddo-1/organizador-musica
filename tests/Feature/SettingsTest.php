<?php

use App\Services\Settings;

beforeEach(fn () => $this->settings = app(Settings::class));

it('falls back to config/music.php', function () {
    expect($this->settings->musicFolder())->toBe(config('music.default_folder'))
        ->and($this->settings->playlistsFolder())->toBe(config('music.playlists_folder'))
        ->and($this->settings->defaultGenre())->toBe('Drum and Bass')
        ->and($this->settings->subfolderAsSubgenre())->toBeTrue();
});

it('keeps what the user changes', function () {
    $this->settings->setMusicFolder('D:/DJ/Drum and Bass/');
    $this->settings->setPlaylistsFolder('D:\DJ\Playlists');
    $this->settings->setDefaultGenre('  Neurofunk ');
    $this->settings->setSubfolderAsSubgenre(false);

    $fresh = new Settings;

    expect($fresh->musicFolder())->toBe('D:\DJ\Drum and Bass')
        ->and($fresh->playlistsFolder())->toBe('D:\DJ\Playlists')
        ->and($fresh->defaultGenre())->toBe('Neurofunk')
        ->and($fresh->subfolderAsSubgenre())->toBeFalse();
});

it('leaves the genre to the tags when the default genre is emptied', function () {
    $this->settings->setDefaultGenre('');

    expect($this->settings->defaultGenre())->toBeNull();
});

it('goes back to the default playlists folder when it is emptied', function () {
    $this->settings->setPlaylistsFolder('D:\DJ\Playlists');
    $this->settings->setPlaylistsFolder('');

    expect($this->settings->playlistsFolder())->toBe(config('music.playlists_folder'));
});

it('stores folders typed inside WSL in Windows form', function () {
    config(['music.wsl' => true]);

    $this->settings->setMusicFolder('/mnt/c/Users/Ricardo/Music/Musikote/DNB');

    expect($this->settings->musicFolder())->toBe('C:\Users\Ricardo\Music\Musikote\DNB');
});
