<?php

it('reads the music settings defaults from config', function () {
    expect(config('music.default_genre'))->toBe('Drum and Bass')
        ->and(config('music.subfolder_as_subgenre'))->toBeTrue()
        ->and(config('music.extensions'))->toBe(['mp3', 'aiff', 'aif', 'wav', 'flac', 'm4a', 'ogg'])
        ->and(config('music.tag_categories'))->toBe(['subgenre' => 'Subgénero', 'percussion' => 'Percusión'])
        ->and(config('music.backups_to_keep'))->toBe(10);
});

it('stores the playlists in storage when no folder is configured', function () {
    expect(config('music.playlists_folder'))->toBe(storage_path('app/playlists'));
});
