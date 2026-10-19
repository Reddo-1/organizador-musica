<?php

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\Settings;

/** A musica.db shaped like the Python version's (SPEC §5.11). */
function legacyDatabase(): string
{
    $file = temporaryFolder().DIRECTORY_SEPARATOR.'musica.db';
    $db = new PDO("sqlite:{$file}");
    $db->exec(<<<'SQL'
        create table generos (id integer primary key, nombre text);
        create table canciones (id integer primary key, ruta text, titulo text, artista text, genero_id integer,
            anio_cancion integer, anio_descubrimiento integer, energia integer, funcion text, bpm real,
            tonalidad text, duracion real, notas text, falta integer);
        create table etiquetas (id integer primary key, categoria text, nombre text);
        create table cancion_etiqueta (cancion_id integer, etiqueta_id integer);
        create table ajustes (clave text primary key, valor text);
        insert into generos values (1, 'Drum and Bass'), (2, 'Neurofunk');
        insert into canciones values
            (1, 'C:\Users\Ricardo\Music\Musikote\DNB\Neuro\Kanine - Sundown.mp3', 'Sundown', 'Kanine', 2, 2020, 2021, 5, 'Peso', 174.0, '8A', 312.4, 'Para el pico', 0),
            (2, 'C:\Users\Ricardo\Music\Musikote\DNB\Deep\Rova - Niebla.aiff', 'Niebla', 'Rova', 1, 2015, 2019, 2, 'Respiro', 172.5, '5A', 280, null, 1),
            (3, 'C:\Users\Ricardo\Music\Musikote\DNB\01 - Phibes - Ruido.mp3', null, null, null, null, null, null, null, null, null, null, null, 0);
        insert into etiquetas values (1, 'subgenero', 'Neurofunk'), (2, 'percusion', 'Rolling'), (3, 'subgénero', 'Deep'), (4, 'vocal', 'Sí');
        insert into cancion_etiqueta values (1, 1), (1, 2), (2, 3), (2, 4);
        insert into ajustes values ('carpeta_musica', 'C:\Users\Ricardo\Music\Musikote\DNB'), ('carpeta_playlists', 'C:\Users\Ricardo\Music\Playlists'),
            ('genero_defecto', 'Drum and Bass'), ('subcarpeta_subgenero', 'True');
        SQL);

    return $file;
}

it('imports songs, genres, tags and settings from the Python version', function () {
    $file = legacyDatabase();

    $this->artisan('music:import-legacy', ['path' => $file])
        ->expectsOutputToContain('Temas nuevos')
        ->expectsOutputToContain('vocal')
        ->assertSuccessful();

    $sundown = Song::query()->where('title', 'Sundown')->sole();
    $niebla = Song::query()->where('title', 'Niebla')->sole();
    $untitled = Song::query()->where('path', 'like', '%Phibes%')->sole();

    expect(Song::count())->toBe(3)
        ->and($sundown->artist)->toBe('Kanine')
        ->and($sundown->genre->name)->toBe('Neurofunk')
        ->and($sundown->energy)->toBe(5)
        ->and($sundown->set_role)->toBe(SetRole::Weight)
        ->and($sundown->bpm)->toBe(174.0)
        ->and($sundown->musical_key)->toBe('8A')
        ->and($sundown->duration)->toBe(312.4)
        ->and($sundown->notes)->toBe('Para el pico')
        ->and($sundown->tags->pluck('name')->sort()->values()->all())->toBe(['Neurofunk', 'Rolling'])
        ->and($sundown->tagsIn('percussion')->pluck('name')->all())->toBe(['Rolling'])
        ->and($niebla->set_role)->toBe(SetRole::Breather)
        ->and($niebla->missing)->toBeTrue()
        ->and($niebla->tagsIn('subgenre')->pluck('name')->all())->toBe(['Deep'])
        ->and($untitled->title)->toBe('Ruido')
        ->and($untitled->artist)->toBeNull()
        ->and(Genre::count())->toBe(2)
        ->and(app(Settings::class)->musicFolder())->toBe('C:\Users\Ricardo\Music\Musikote\DNB')
        ->and(app(Settings::class)->playlistsFolder())->toBe('C:\Users\Ricardo\Music\Playlists')
        ->and(app(Settings::class)->subfolderAsSubgenre())->toBeTrue();
});

it('can run again without duplicating anything', function () {
    $file = legacyDatabase();

    $this->artisan('music:import-legacy', ['path' => $file])->assertSuccessful();
    Song::query()->where('title', 'Sundown')->sole()->update(['energy' => 1]);
    $this->artisan('music:import-legacy', ['path' => $file])->expectsOutputToContain('Temas actualizados')->assertSuccessful();

    expect(Song::count())->toBe(3)
        ->and(Tag::count())->toBe(4)
        ->and(Song::query()->where('title', 'Sundown')->sole()->energy)->toBe(5);
});

it('matches songs already in the library by path, ignoring case', function () {
    $existing = Song::factory()->create(['path' => 'c:/users/ricardo/music/musikote/dnb/neuro/KANINE - SUNDOWN.MP3']);

    $this->artisan('music:import-legacy', ['path' => legacyDatabase()])->assertSuccessful();

    expect(Song::count())->toBe(3)->and($existing->fresh()->energy)->toBe(5);
});

it('only reads the old database', function () {
    $file = legacyDatabase();
    $before = md5_file($file);

    $this->artisan('music:import-legacy', ['path' => $file])->assertSuccessful();

    expect(md5_file($file))->toBe($before);
});

it('says when the file is not there or is not the Python database', function () {
    $this->artisan('music:import-legacy', ['path' => temporaryFolder().'/no-esta.db'])->assertFailed();

    $other = temporaryFolder().DIRECTORY_SEPARATOR.'otra.db';
    (new PDO("sqlite:{$other}"))->exec('create table cosas (id integer)');

    $this->artisan('music:import-legacy', ['path' => $other])->expectsOutputToContain('canciones')->assertFailed();
});
