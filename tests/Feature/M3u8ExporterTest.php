<?php

use App\Models\Song;
use App\Services\M3u8Exporter;
use App\Services\Settings;
use App\Support\LocalPath;

beforeEach(function () {
    $this->songs = [
        Song::factory()->make([
            'artist' => 'Gladde Paling',
            'title' => 'Griezelig',
            'duration' => 245.6,
            'path' => 'C:\Users\Ricardo\Music\Musikote\DNB\Gladde Paling\griezelig.mp3',
        ]),
        Song::factory()->make([
            'artist' => 'Ñandú',
            'title' => 'Canción de la niña',
            'duration' => null,
            'path' => 'C:\Users\Ricardo\Music\Musikote\DNB\Más allá\Ñandú - Canción de la niña.aiff',
        ]),
        Song::factory()->make([
            'artist' => null,
            'title' => 'Sin artista',
            'duration' => 300.4,
            'path' => 'D:\Sueltos\sin artista.wav',
        ]),
    ];
});

it('writes the exact M3U8 format, accents included', function () {
    expect(app(M3u8Exporter::class)->render($this->songs))->toBe(
        "#EXTM3U\r\n"
        ."#EXTINF:246,Gladde Paling - Griezelig\r\n"
        ."C:\\Users\\Ricardo\\Music\\Musikote\\DNB\\Gladde Paling\\griezelig.mp3\r\n"
        ."#EXTINF:-1,Ñandú - Canción de la niña\r\n"
        ."C:\\Users\\Ricardo\\Music\\Musikote\\DNB\\Más allá\\Ñandú - Canción de la niña.aiff\r\n"
        ."#EXTINF:300,Sin artista\r\n"
        ."D:\\Sueltos\\sin artista.wav\r\n"
    );
});

it('writes UTF-8 without a BOM and only CRLF line endings', function () {
    $content = app(M3u8Exporter::class)->render($this->songs);

    expect(mb_check_encoding($content, 'UTF-8'))->toBeTrue()
        ->and(str_starts_with($content, "\xEF\xBB\xBF"))->toBeFalse()
        ->and(preg_match("/(?<!\r)\n/", $content))->toBe(0);
});

it('keeps a label on one line', function () {
    $song = Song::factory()->make(['artist' => "Kanine\r\n", 'title' => 'Sundown', 'duration' => 1]);

    expect(app(M3u8Exporter::class)->render([$song]))->toContain("#EXTINF:1,Kanine  - Sundown\r\n");
});

it('cleans the file name', function (string $name, string $file) {
    expect(M3u8Exporter::fileName($name))->toBe($file);
})->with([
    ['Set 2026-10-19', 'Set 2026-10-19.m3u8'],
    ['Neuro/Deep: <cierre> "techstep" | ¿pico?*', 'NeuroDeep cierre techstep  ¿pico.m3u8'],
    ["Con\tcontrol\x01", 'Concontrol.m3u8'],
    ['Acaba en punto.', 'Acaba en punto.m3u8'],
    ['???', 'playlist.m3u8'],
]);

it('saves into the playlists folder and returns the final path', function () {
    $folder = temporaryFolder().DIRECTORY_SEPARATOR.'Playlists';
    app(Settings::class)->setPlaylistsFolder($folder);

    $path = app(M3u8Exporter::class)->save('Set ñ 2026-10-19', $this->songs);

    expect($path)->toBe(LocalPath::toStored($folder.DIRECTORY_SEPARATOR.'Set ñ 2026-10-19.m3u8'))
        ->and(file_get_contents(LocalPath::toLocal($path)))->toBe(app(M3u8Exporter::class)->render($this->songs));
});
