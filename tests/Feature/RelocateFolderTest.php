<?php

use App\Models\Song;
use App\Support\LocalPath;

it('moves the base folder of every song under it, and only those', function () {
    config(['music.wsl' => false]);
    $moved = Song::factory()->create(['path' => 'D:\Musica\DNB\Neuro\Kanine - Sundown.mp3']);
    $nested = Song::factory()->create(['path' => 'd:\musica\dnb\Deep\Rova - Niebla.aiff']);
    $other = Song::factory()->create(['path' => 'D:\Musica\DNB-otros\x.mp3']);

    $this->artisan('music:relocate-folder', ['from' => 'D:\Musica\DNB', 'to' => 'C:\Users\Ricardo\Music\DNB', '--force' => true])
        ->expectsOutputToContain('2 temas con la ruta cambiada')
        ->assertSuccessful();

    expect($moved->fresh()->path)->toBe('C:\Users\Ricardo\Music\DNB\Neuro\Kanine - Sundown.mp3')
        ->and($moved->fresh()->path_key)->toBe(LocalPath::key('C:\Users\Ricardo\Music\DNB\Neuro\Kanine - Sundown.mp3'))
        ->and($nested->fresh()->path)->toBe('C:\Users\Ricardo\Music\DNB\Deep\Rova - Niebla.aiff')
        ->and($other->fresh()->path)->toBe('D:\Musica\DNB-otros\x.mp3');
});

it('marks whether the file is found at the new place', function () {
    $folder = temporaryFolder();
    putTrack($folder, 'Neuro/Kanine - Sundown.mp3');
    $song = Song::factory()->create(['path' => 'Q:\Viejo\Neuro\Kanine - Sundown.mp3', 'missing' => true]);

    $this->artisan('music:relocate-folder', ['from' => 'Q:\Viejo', 'to' => $folder, '--force' => true])->assertSuccessful();

    expect($song->fresh()->missing)->toBeFalse()
        ->and(file_exists($song->fresh()->localPath()))->toBeTrue();
});
