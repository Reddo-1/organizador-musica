<?php

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Services\SongRelocator;

beforeEach(fn () => $this->relocator = app(SongRelocator::class));

it('suggests the eight present songs with the most similar file name, most alike first', function () {
    $missing = Song::factory()->missing()->create(['path' => 'C:\DNB\Kanine - Sundown.mp3']);
    $best = Song::factory()->create(['path' => 'C:\DNB\Nuevos\Kanine - Sundown (VIP).aiff']);
    $close = Song::factory()->create(['path' => 'C:\DNB\Kanine - Sundowns.mp3']);
    Song::factory()->count(10)->create();
    Song::factory()->missing()->create(['path' => 'C:\DNB\Kanine - Sundown 2.mp3']);

    $suggestions = $this->relocator->suggestions($missing);

    expect($suggestions)->toHaveCount(8)
        ->and($suggestions[0]['song']->is($close))->toBeTrue()
        ->and($suggestions[1]['song']->is($best))->toBeTrue()
        ->and($suggestions[0]['similarity'])->toBeGreaterThanOrEqual($suggestions[1]['similarity'])
        ->and($suggestions->pluck('similarity')->all())->toBe($suggestions->pluck('similarity')->sortDesc()->values()->all())
        ->and($suggestions->every(fn (array $s): bool => ! $s['song']->missing))->toBeTrue();
});

it('moves the missing song onto the renamed file and deletes the duplicate', function () {
    $neuro = Tag::findOrCreateNamed('subgenre', 'Neurofunk');
    $rolling = Tag::findOrCreateNamed('percussion', 'Rolling');
    $genre = Genre::findOrCreateNamed('Drum and Bass');

    $missing = Song::factory()->missing()->create([
        'path' => 'C:\DNB\kanine sundown.mp3', 'energy' => 5, 'set_role' => SetRole::Weight,
        'genre_id' => null, 'duration' => 300, 'notes' => 'Cierre',
    ]);
    $missing->tags()->attach($neuro);

    $duplicate = Song::factory()->create([
        'path' => 'C:\DNB\Kanine - Sundown.aiff', 'genre_id' => $genre->id, 'duration' => 312.5,
    ]);
    $duplicate->tags()->attach([$neuro->id, $rolling->id]);
    $playlist = Playlist::factory()->create();
    $playlist->songs()->attach($duplicate->id, ['position' => 3]);

    $song = $this->relocator->relocate($missing, $duplicate);

    expect(Song::find($duplicate->id))->toBeNull()
        ->and($song->path)->toBe('C:\DNB\Kanine - Sundown.aiff')
        ->and($song->missing)->toBeFalse()
        ->and($song->energy)->toBe(5)
        ->and($song->set_role)->toBe(SetRole::Weight)
        ->and($song->notes)->toBe('Cierre')
        ->and($song->genre_id)->toBe($genre->id)
        ->and($song->duration)->toBe(312.5)
        ->and($song->tags()->pluck('name')->sort()->values()->all())->toBe(['Neurofunk', 'Rolling'])
        ->and($playlist->songs()->pluck('songs.id')->all())->toBe([$song->id]);
});

it('keeps the original genre when it had one', function () {
    $original = Genre::findOrCreateNamed('Neurofunk');
    $missing = Song::factory()->missing()->for($original)->create();
    $duplicate = Song::factory()->for(Genre::findOrCreateNamed('Drum and Bass'))->create();

    expect($this->relocator->relocate($missing, $duplicate)->genre_id)->toBe($original->id);
});

it('removes a song from the app without touching any file', function () {
    $path = putTrack(temporaryFolder(), 'Kanine - Sundown.mp3');
    $song = Song::factory()->create(['path' => $path]);

    $this->relocator->remove($song);

    expect(Song::count())->toBe(0)
        ->and(file_exists($path))->toBeTrue();
});
