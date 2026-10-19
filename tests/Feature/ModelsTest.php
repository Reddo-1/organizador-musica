<?php

use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use Illuminate\Database\QueryException;

it('keeps path_key in step with path', function () {
    $song = Song::factory()->create(['path' => 'C:/Users/Ricardo/MÚSICA/Ñandú.MP3']);

    expect($song->path_key)->toBe('c:\users\ricardo\música\ñandú.mp3');

    $song->update(['path' => 'D:\Otra\Ñandú.aiff']);

    expect($song->fresh()->path_key)->toBe('d:\otra\ñandú.aiff');
});

it('treats tag and genre names as unique ignoring case', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'Halftime');
    $genre = Genre::findOrCreateNamed('Drum and Bass');

    expect(Tag::findOrCreateNamed('subgenre', ' halftime ')->is($tag))->toBeTrue()
        ->and(Tag::findOrCreateNamed('percussion', 'Halftime')->is($tag))->toBeFalse()
        ->and(Genre::findOrCreateNamed('DRUM AND BASS')->is($genre))->toBeTrue()
        ->and(Tag::findOrCreateNamed('subgenre', 'Ñu')->is(Tag::findOrCreateNamed('subgenre', 'ñu')))->toBeTrue();
});

it('rejects a duplicate name in the database too', function () {
    Tag::create(['category' => 'subgenre', 'name' => 'Halftime']);
    Tag::create(['category' => 'subgenre', 'name' => 'halftime']);
})->throws(QueryException::class);

it('cleans up relations when a song or a genre goes', function () {
    $genre = Genre::factory()->create();
    $song = Song::factory()->for($genre)->create();
    $other = Song::factory()->for($genre)->create();
    $song->tags()->attach(Tag::factory()->create());
    $playlist = Playlist::factory()->create();
    $playlist->songs()->attach([$song->id => ['position' => 1], $other->id => ['position' => 2]]);

    $song->delete();
    $genre->delete();

    expect(DB::table('song_tag')->count())->toBe(0)
        ->and($playlist->songs()->pluck('songs.id')->all())->toBe([$other->id])
        ->and($other->fresh()->genre_id)->toBeNull();
});

it('lists a playlist in its order', function () {
    $playlist = Playlist::factory()->create();
    [$a, $b, $c] = Song::factory()->count(3)->create();
    $playlist->songs()->attach([$a->id => ['position' => 3], $b->id => ['position' => 1], $c->id => ['position' => 2]]);

    expect($playlist->songs->pluck('id')->all())->toBe([$b->id, $c->id, $a->id]);
});

it('names a song as the m3u8 does', function () {
    expect(Song::factory()->make(['artist' => 'Phibes', 'title' => 'Ruido'])->displayName())->toBe('Phibes - Ruido')
        ->and(Song::factory()->make(['artist' => null, 'title' => 'Ruido'])->displayName())->toBe('Ruido');
});
