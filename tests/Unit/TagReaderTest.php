<?php

use App\Exceptions\UnreadableAudioFile;
use App\Services\TagReader;

beforeEach(fn () => $this->reader = new TagReader);

it('reads an ID3v2.4 MP3', function () {
    $track = $this->reader->read(audio('tagged-v24.mp3'));

    expect($track->title)->toBe('Sombra Eléctrica')
        ->and($track->artist)->toBe('Kanine')
        ->and($track->songYear)->toBe(2019)
        ->and($track->bpm)->toBe(174.0)
        ->and($track->musicalKey)->toBe('8A')
        ->and($track->genre)->toBe('Neurofunk')
        ->and($track->duration)->toBeGreaterThan(0.9)->toBeLessThan(1.1);
});

it('reads the year from TYER in ID3v2.3', function () {
    $track = $this->reader->read(audio('tagged-v23.mp3'));

    expect($track->songYear)->toBe(2016)
        ->and($track->musicalKey)->toBe('11B')
        ->and($track->genre)->toBe('Techstep');
});

it('reads every supported format', function (string $file, string $title, string $artist, int $year, ?float $bpm, ?string $key) {
    $track = $this->reader->read(audio($file));

    expect($track->title)->toBe($title)
        ->and($track->artist)->toBe($artist)
        ->and($track->songYear)->toBe($year)
        ->and($track->bpm)->toBe($bpm)
        ->and($track->musicalKey)->toBe($key)
        ->and($track->duration)->toEqualWithDelta(1.0, 0.1);
})->with([
    'AIFF' => ['tagged.aiff', 'Sombra Eléctrica', 'Kanine', 2019, 174.0, '8A'],
    'FLAC' => ['tagged.flac', 'Bajo Cero', 'MaySev', 2021, 170.0, '4A'],
    'M4A' => ['tagged.m4a', 'Túnel', 'Frannabik', 2018, 174.0, '6A'],
    'OGG' => ['tagged.ogg', 'Niebla', 'LSB', 2015, 175.0, '9B'],
    'WAV' => ['tagged.wav', 'Pulso', 'AirGlo', 2022, null, '2B'],
]);

it('parses "01 - Artista - Título" when the file has no tags', function () {
    $path = putTrack(temporaryFolder(), '01 - Artista - Título.mp3', 'untagged.mp3');

    $track = $this->reader->read($path);

    expect($track->artist)->toBe('Artista')
        ->and($track->title)->toBe('Título');
});

it('takes the artist and title from the file name', function (string $stem, ?string $artist, string $title) {
    expect(TagReader::parseFileName($stem))->toBe(['artist' => $artist, 'title' => $title]);
})->with([
    ['01 - Artista - Título', 'Artista', 'Título'],
    ['093. Chase & Status - BACKBONE', 'Chase & Status', 'BACKBONE'],
    ['1. Maysev - Left', 'Maysev', 'Left'],
    ['Chase & Status - RTRN II JUNGLE - 07 - Weed & Rum', 'Chase & Status', 'RTRN II JUNGLE - 07 - Weed & Rum'],
    ['01 Prelude', null, 'Prelude'],
    ['02-frannabik-eyes', null, 'frannabik-eyes'],
    ['griezelig', null, 'griezelig'],
    ['2024 - Remix', '2024', 'Remix'],
]);

it('finds the Camelot key in a comment like "5A - Energy 7"', function () {
    expect($this->reader->read(audio('camelot-comment.mp3'))->musicalKey)->toBe('5A');
});

it('finds the Camelot key in a COMM frame as Mixed In Key writes it', function () {
    expect($this->reader->read(audio('camelot-comm.mp3'))->musicalKey)->toBe('8A');
});

it('ignores a year out of range and an unbelievable BPM', function () {
    $track = $this->reader->read(audio('old-year.mp3'));

    expect($track->songYear)->toBeNull()
        ->and($track->bpm)->toBeNull()
        ->and($track->title)->toBe('Año Raro');
});

it('takes the discovered year from the older of creation and modification', function () {
    $path = putTrack(temporaryFolder(), 'Kanine - Sundown.mp3');
    touch($path, mktime(12, 0, 0, 6, 1, 2018));

    expect($this->reader->read($path)->discoveredYear)->toBe(2018);
});

it('names the first level of subfolder under the scanned folder', function () {
    $folder = temporaryFolder();

    expect($this->reader->read(putTrack($folder, 'JumpUp/x.mp3'), $folder)->subfolder)->toBe('JumpUp')
        ->and($this->reader->read(putTrack($folder, 'Deep/Halftime/y.mp3'), $folder)->subfolder)->toBe('Deep')
        ->and($this->reader->read(putTrack($folder, 'z.mp3'), $folder)->subfolder)->toBeNull();
});

it('refuses a file that is not audio', function () {
    $path = temporaryFolder().'/roto.mp3';
    file_put_contents($path, str_repeat('esto no es audio ', 200));

    $this->reader->read($path);
})->throws(UnreadableAudioFile::class);

it('refuses a file that does not exist', function () {
    $this->reader->read(temporaryFolder().'/no-existe.mp3');
})->throws(UnreadableAudioFile::class);
