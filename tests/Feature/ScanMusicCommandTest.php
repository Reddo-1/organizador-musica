<?php

use App\Models\Song;

it('scans a folder and prints the summary', function () {
    $folder = temporaryFolder();
    putTrack($folder, 'Neuro/Kanine - Sombra.mp3');
    putTrack($folder, 'Deep/Rova - Niebla.ogg', 'tagged.ogg');

    $this->artisan('music:scan', ['folder' => $folder])
        ->expectsOutputToContain('Archivos en la carpeta')
        ->expectsOutputToContain('Nuevos')
        ->expectsOutputToContain('Reubicados')
        ->expectsOutputToContain('No encontrados')
        ->expectsOutputToContain('Errores')
        ->assertSuccessful();

    expect(Song::count())->toBe(2);
});

it('lists what moved and what is missing', function () {
    $folder = temporaryFolder();
    $moved = putTrack($folder, 'Viejo/Kanine - Sombra.mp3');
    $gone = putTrack($folder, 'Rova - Niebla.mp3', 'tagged-v23.mp3');
    $this->artisan('music:scan', ['folder' => $folder])->assertSuccessful();

    unlink($moved);
    unlink($gone);
    putTrack($folder, 'Nuevo/Kanine - Sombra.aiff', 'tagged.aiff');

    $this->artisan('music:scan', ['folder' => $folder])
        ->expectsOutputToContain('Kanine - Sombra.aiff')
        ->expectsOutputToContain('Rova - Niebla.mp3')
        ->assertSuccessful();
});

it('fails when the folder does not exist', function () {
    $this->artisan('music:scan', ['folder' => temporaryFolder().'/no-existe'])
        ->expectsOutputToContain('No existe la carpeta de música')
        ->assertFailed();
});
