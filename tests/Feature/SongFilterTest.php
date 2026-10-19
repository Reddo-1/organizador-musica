<?php

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\SongFilter;

beforeEach(function () {
    $this->filter = app(SongFilter::class);
    $this->neuro = Tag::findOrCreateNamed('subgenre', 'Neurofunk');
    $this->deep = Tag::findOrCreateNamed('subgenre', 'Deep');
    $this->rolling = Tag::findOrCreateNamed('percussion', 'Rolling');

    $this->both = Song::factory()->create(['title' => 'Neuro y Deep', 'energy' => 4, 'song_year' => 2015, 'bpm' => 174]);
    $this->both->tags()->attach([$this->neuro->id, $this->deep->id, $this->rolling->id]);
    $this->neuroOnly = Song::factory()->create(['title' => 'Solo neuro', 'energy' => 5, 'song_year' => 2020, 'bpm' => 172]);
    $this->neuroOnly->tags()->attach($this->neuro);
    $this->deepOnly = Song::factory()->create(['title' => 'Solo deep', 'energy' => 2, 'song_year' => 2008, 'bpm' => 170]);
    $this->deepOnly->tags()->attach($this->deep);
    $this->bare = Song::factory()->create(['title' => 'Sin datos', 'energy' => null, 'song_year' => null, 'bpm' => null]);
});

function titles(array $filters): array
{
    return app(SongFilter::class)->apply($filters)->orderBy('title')->pluck('title')->all();
}

it('includes songs with any of the chosen tags (OR)', function () {
    expect(titles(['tags' => ['subgenre' => ['include' => [$this->neuro->id, $this->deep->id], 'mode' => 'any']]]))
        ->toBe(['Neuro y Deep', 'Solo deep', 'Solo neuro']);
});

it('includes only songs with all of the chosen tags (AND)', function () {
    expect(titles(['tags' => ['subgenre' => ['include' => [$this->neuro->id, $this->deep->id], 'mode' => 'all']]]))
        ->toBe(['Neuro y Deep']);
});

it('excludes songs with any of the excluded tags (NOT)', function () {
    expect(titles(['tags' => ['subgenre' => ['exclude' => [$this->deep->id]]]]))
        ->toBe(['Sin datos', 'Solo neuro']);
});

it('combines categories', function () {
    expect(titles([
        'tags' => [
            'subgenre' => ['include' => [$this->neuro->id], 'mode' => 'any'],
            'percussion' => ['exclude' => [$this->rolling->id]],
        ],
    ]))->toBe(['Solo neuro']);
});

it('filters a narrower range and leaves out songs without the value', function () {
    expect(titles(['energy' => [4, 5]]))->toBe(['Neuro y Deep', 'Solo neuro'])
        ->and(titles(['song_year' => [2010, 2018]]))->toBe(['Neuro y Deep'])
        ->and(titles(['bpm' => [171.5, 174]]))->toBe(['Neuro y Deep', 'Solo neuro']);
});

it('does not filter with a range that spans the whole library, so songs without the value stay', function () {
    $all = ['Neuro y Deep', 'Sin datos', 'Solo deep', 'Solo neuro'];

    expect(titles(['energy' => [1, 5]]))->toBe($all)
        ->and(titles(['energy' => [2, 5]]))->toBe($all)
        ->and(titles(['song_year' => [2008, 2020], 'bpm' => [170, 174]]))->toBe($all)
        ->and(titles(['song_year' => [1990, 2030]]))->toBe($all);
});

it('leaves out songs whose file is missing unless asked', function () {
    $this->neuroOnly->update(['missing' => true]);

    expect(titles([]))->not->toContain('Solo neuro')
        ->and(titles(['include_missing' => true]))->toContain('Solo neuro');
});

it('filters by genre and by set role', function () {
    $techstep = Genre::findOrCreateNamed('Techstep');
    $this->deepOnly->update(['genre_id' => $techstep->id, 'set_role' => SetRole::Breather]);
    $this->both->update(['set_role' => SetRole::Weight]);

    expect(titles(['genres' => [$techstep->id]]))->toBe(['Solo deep'])
        ->and(titles(['roles' => ['weight', 'breather']]))->toBe(['Neuro y Deep', 'Solo deep']);
});

it('knows the bounds of the library', function () {
    expect($this->filter->bounds())->toBe([
        'energy' => [2, 5],
        'song_year' => [2008, 2020],
        'discovered_year' => $this->filter->bounds()['discovered_year'],
        'bpm' => [170.0, 174.0],
    ]);
});

it('cleans up whatever was stored', function () {
    $normalized = SongFilter::normalize(['genres' => ['3', 'x'], 'roles' => ['open', 'boss'], 'energy' => [5, 2], 'tags' => ['subgenre' => ['mode' => 'raro']]]);

    expect($normalized['genres'])->toBe([3])
        ->and($normalized['roles'])->toBe(['open'])
        ->and($normalized['energy'])->toBe([2, 5])
        ->and($normalized['tags']['subgenre'])->toBe(['include' => [], 'mode' => 'any', 'exclude' => []])
        ->and($normalized['include_missing'])->toBeFalse();
});
