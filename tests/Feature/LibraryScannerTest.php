<?php

use App\Enums\SetRole;
use App\Exceptions\MusicFolderNotFound;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\LibraryScanner;
use App\Services\Settings;
use App\Support\LocalPath;

beforeEach(function () {
    $this->folder = temporaryFolder();
    $this->scan = fn () => app(LibraryScanner::class)->scan($this->folder);
});

it('adds new files with what their tags say', function () {
    putTrack($this->folder, 'Kanine - Sombra.mp3', 'tagged-v24.mp3');
    putTrack($this->folder, 'Bajo Cero.flac', 'tagged.flac');

    $result = ($this->scan)();

    expect($result->filesInFolder)->toBe(2)
        ->and($result->new)->toHaveCount(2)
        ->and($result->errors)->toBe([]);

    $song = Song::query()->where('title', 'Sombra Eléctrica')->sole();

    expect($song->artist)->toBe('Kanine')
        ->and($song->song_year)->toBe(2019)
        ->and($song->bpm)->toBe(174.0)
        ->and($song->musical_key)->toBe('8A')
        ->and($song->genre->name)->toBe('Drum and Bass')
        ->and($song->path)->toBe(LocalPath::toStored($this->folder.DIRECTORY_SEPARATOR.'Kanine - Sombra.mp3'))
        ->and($song->path_key)->toBe(LocalPath::key($song->path));
});

it('uses the genre from the tags when the default genre is empty', function () {
    app(Settings::class)->setDefaultGenre('');
    putTrack($this->folder, 'x.mp3', 'tagged-v23.mp3');

    ($this->scan)();

    expect(Song::sole()->genre->name)->toBe('Techstep');
});

it('only looks at audio extensions and skips macOS "._" files', function () {
    putTrack($this->folder, 'a.mp3');
    putTrack($this->folder, '._a.mp3');
    putTrack($this->folder, 'cover.jpg');
    putTrack($this->folder, 'Sub/B.AIFF', 'tagged.aiff');

    expect(($this->scan)()->filesInFolder)->toBe(2)
        ->and(Song::count())->toBe(2);
});

it('turns the first level of subfolder into a subgenre', function () {
    putTrack($this->folder, 'JumpUp/Kanine - Sundown.mp3');
    putTrack($this->folder, 'Deep/Halftime/Rova - Niebla.mp3', 'tagged.ogg');
    putTrack($this->folder, 'Suelto.mp3', 'tagged-v23.mp3');

    ($this->scan)();

    $subgenres = fn (string $title): array => Song::query()->where('title', $title)->sole()
        ->tagsIn(Tag::SUBGENRE)->pluck('name')->all();

    expect($subgenres('Sombra Eléctrica'))->toBe(['JumpUp'])
        ->and($subgenres('Niebla'))->toBe(['Deep'])
        ->and($subgenres('Ruido de Fondo'))->toBe([]);
});

it('does not tag subfolders when that setting is off', function () {
    app(Settings::class)->setSubfolderAsSubgenre(false);
    putTrack($this->folder, 'JumpUp/x.mp3');

    ($this->scan)();

    expect(Tag::count())->toBe(0);
});

it('relocates a song converted from mp3 to aiff and keeps its classification', function () {
    $mp3 = putTrack($this->folder, 'Neuro/Kanine - Sombra.mp3');
    ($this->scan)();

    $song = Song::sole();
    $song->update(['energy' => 4, 'set_role' => SetRole::Weight, 'notes' => 'Para el pico']);
    $song->tags()->attach(Tag::findOrCreateNamed('percussion', 'Rolling'));

    unlink($mp3);
    $aiff = putTrack($this->folder, 'Neuro/kanine - sombra.aiff', 'tagged.aiff');

    $result = ($this->scan)();
    $song->refresh();

    expect($result->relocated)->toBe([[
        'from' => LocalPath::toStored($mp3),
        'to' => LocalPath::toStored($aiff),
    ]])
        ->and($result->new)->toBe([])
        ->and($result->missing)->toBe([])
        ->and(Song::count())->toBe(1)
        ->and($song->path)->toBe(LocalPath::toStored($aiff))
        ->and($song->path_key)->toBe(LocalPath::key(LocalPath::toStored($aiff)))
        ->and($song->energy)->toBe(4)
        ->and($song->set_role)->toBe(SetRole::Weight)
        ->and($song->notes)->toBe('Para el pico')
        ->and($song->tags->pluck('name')->sort()->values()->all())->toBe(['Neuro', 'Rolling']);
});

it('relocates a file moved to another subfolder', function () {
    $old = putTrack($this->folder, 'Nuevos/Kanine - Sombra.mp3');
    ($this->scan)();
    unlink($old);
    $new = putTrack($this->folder, 'Neuro/Kanine - Sombra.mp3');

    $result = ($this->scan)();

    expect($result->relocated)->toHaveCount(1)
        ->and(Song::sole()->path)->toBe(LocalPath::toStored($new));
});

it('does not guess when the name is ambiguous', function () {
    $first = putTrack($this->folder, 'A/Intro.mp3');
    $second = putTrack($this->folder, 'B/Intro.mp3');
    ($this->scan)();
    unlink($first);
    unlink($second);
    putTrack($this->folder, 'C/Intro.aiff', 'tagged.aiff');

    $result = ($this->scan)();

    expect($result->relocated)->toBe([])
        ->and($result->missing)->toHaveCount(2)
        ->and($result->new)->toHaveCount(1);
});

it('does not guess when two new files share the name', function () {
    $old = putTrack($this->folder, 'Intro.mp3');
    ($this->scan)();
    unlink($old);
    putTrack($this->folder, 'A/Intro.aiff', 'tagged.aiff');
    putTrack($this->folder, 'B/Intro.wav', 'tagged.wav');

    $result = ($this->scan)();

    expect($result->relocated)->toBe([])
        ->and($result->missing)->toHaveCount(1)
        ->and($result->new)->toHaveCount(2);
});

it('marks missing songs and recovers them when the file is back', function () {
    $path = putTrack($this->folder, 'Kanine - Sombra.mp3');
    ($this->scan)();

    rename($path, $path.'.bak');
    $result = ($this->scan)();

    expect($result->missing)->toBe([LocalPath::toStored($path)])
        ->and(Song::sole()->missing)->toBeTrue();

    rename($path.'.bak', $path);
    $result = ($this->scan)();

    expect($result->recovered)->toBe([LocalPath::toStored($path)])
        ->and($result->missing)->toBe([])
        ->and(Song::sole()->missing)->toBeFalse();
});

it('checks songs outside the scanned folder against the disk', function () {
    $elsewhere = temporaryFolder();
    $kept = putTrack($elsewhere, 'kept.mp3');

    Song::factory()->create(['path' => LocalPath::toStored($kept)]);
    $gone = Song::factory()->create(['path' => LocalPath::toStored($elsewhere.DIRECTORY_SEPARATOR.'gone.mp3')]);

    $result = ($this->scan)();

    expect($result->missing)->toBe([$gone->path])
        ->and(Song::query()->where('missing', true)->pluck('id')->all())->toBe([$gone->id]);
});

it('matches stored paths ignoring case, as Windows does', function () {
    $path = putTrack($this->folder, 'Kanine - Sombra.mp3');
    Song::factory()->create(['path' => mb_strtoupper(LocalPath::toStored($path))]);

    $result = ($this->scan)();

    expect($result->new)->toBe([])
        ->and(Song::count())->toBe(1);
});

it('handles accents and ñ in file names', function () {
    putTrack($this->folder, 'Más Allá/01 - Ñandú - Canción.mp3', 'untagged.mp3');

    ($this->scan)();

    $song = Song::sole();

    expect($song->artist)->toBe('Ñandú')
        ->and($song->title)->toBe('Canción')
        ->and($song->tagsIn(Tag::SUBGENRE)->first()->name)->toBe('Más Allá');
});

it('notes a file it cannot read and carries on', function () {
    putTrack($this->folder, 'Bien.mp3');
    file_put_contents($this->folder.DIRECTORY_SEPARATOR.'Roto.mp3', str_repeat('no es audio ', 200));

    $result = ($this->scan)();

    expect($result->new)->toHaveCount(1)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0]['path'])->toEndWith('Roto.mp3')
        ->and(Song::count())->toBe(1);
});

it('adds nothing on a second scan', function () {
    putTrack($this->folder, 'a.mp3');
    putTrack($this->folder, 'b.mp3', 'tagged-v23.mp3');
    ($this->scan)();

    $result = ($this->scan)();

    expect($result->new)->toBe([])
        ->and(Song::count())->toBe(2)
        ->and(Genre::count())->toBe(1);
});

it('scans the folder from Ajustes by default', function () {
    app(Settings::class)->setMusicFolder($this->folder);
    putTrack($this->folder, 'a.mp3');

    expect(app(LibraryScanner::class)->scan()->new)->toHaveCount(1);
});

it('says so when the folder does not exist', function () {
    app(LibraryScanner::class)->scan($this->folder.DIRECTORY_SEPARATOR.'no-existe');
})->throws(MusicFolderNotFound::class);

it('never touches the audio files', function () {
    $path = putTrack($this->folder, 'Neuro/Kanine - Sombra.mp3');
    $before = [md5_file($path), filemtime($path)];

    ($this->scan)();
    ($this->scan)();

    expect([md5_file($path), filemtime($path)])->toBe($before);
});
