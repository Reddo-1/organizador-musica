<?php

use App\Models\FilterPreset;
use App\Models\Genre;
use App\Models\Playlist;
use App\Models\Song;
use App\Models\Tag;
use App\Services\TagManager;

beforeEach(fn () => $this->manager = app(TagManager::class));

it('creates a tag once, ignoring case', function () {
    $tag = $this->manager->createTag('subgenre', 'Halftime');

    expect($this->manager->createTag('subgenre', ' HALFTIME ')->is($tag))->toBeTrue()
        ->and(Tag::count())->toBe(1);
});

it('refuses a category that is not in the config', function () {
    $this->manager->createTag('vocal', 'Sí');
})->throws(InvalidArgumentException::class);

it('renames a tag', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'Halftime');

    $renamed = $this->manager->renameTag($tag, 'Half-time');

    expect($renamed->is($tag))->toBeTrue()
        ->and($tag->fresh()->name)->toBe('Half-time');
});

it('can change only the case of a name', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'halftime');

    $this->manager->renameTag($tag, 'Halftime');

    expect($tag->fresh()->name)->toBe('Halftime')
        ->and(Tag::count())->toBe(1);
});

it('merges a tag renamed to a name that already exists in its category', function () {
    $halftime = Tag::findOrCreateNamed('subgenre', 'Half-time');
    $neuro = Tag::findOrCreateNamed('subgenre', 'Neurofunk');
    $samePercussionName = Tag::findOrCreateNamed('percussion', 'Neurofunk');
    [$a, $b, $c] = Song::factory()->count(3)->create();
    $a->tags()->attach([$halftime->id]);
    $b->tags()->attach([$halftime->id, $neuro->id]);
    $c->tags()->attach([$neuro->id]);

    $result = $this->manager->renameTag($halftime, 'neurofunk');

    expect($result->is($neuro))->toBeTrue()
        ->and(Tag::find($halftime->id))->toBeNull()
        ->and($neuro->songs()->pluck('songs.id')->sort()->values()->all())->toBe([$a->id, $b->id, $c->id])
        ->and($samePercussionName->fresh()->songs()->count())->toBe(0);
});

it('points saved filters at the tag a merge kept', function () {
    $old = Tag::findOrCreateNamed('subgenre', 'Halftime');
    $kept = Tag::findOrCreateNamed('subgenre', 'Half-time');
    $preset = FilterPreset::factory()->create(['filters' => ['tags' => ['subgenre' => ['include' => [$old->id, $kept->id], 'exclude' => []]]]]);
    $playlist = Playlist::factory()->create(['filters' => ['tags' => ['subgenre' => ['include' => [], 'exclude' => [$old->id]]]]]);

    $this->manager->renameTag($old, 'Half-time');

    expect($preset->fresh()->filters['tags']['subgenre']['include'])->toBe([$kept->id])
        ->and($playlist->fresh()->filters['tags']['subgenre']['exclude'])->toBe([$kept->id]);
});

it('deletes a tag from every song and from saved filters', function () {
    $tag = Tag::findOrCreateNamed('percussion', 'Rolling');
    $song = Song::factory()->create();
    $song->tags()->attach($tag);
    $preset = FilterPreset::factory()->create(['filters' => ['tags' => ['percussion' => ['include' => [$tag->id]]]]]);

    $this->manager->deleteTag($tag);

    expect($song->tags()->count())->toBe(0)
        ->and($preset->fresh()->filters['tags']['percussion']['include'])->toBe([])
        ->and(Song::count())->toBe(1);
});

it('merges genres the same way', function () {
    $dnb = Genre::findOrCreateNamed('Drum and Bass');
    $typo = Genre::findOrCreateNamed('Drum & Bass');
    $song = Song::factory()->for($typo)->create();
    $preset = FilterPreset::factory()->create(['filters' => ['genres' => [$typo->id]]]);

    $result = $this->manager->renameGenre($typo, 'drum and bass');

    expect($result->is($dnb))->toBeTrue()
        ->and($song->fresh()->genre_id)->toBe($dnb->id)
        ->and(Genre::count())->toBe(1)
        ->and($preset->fresh()->filters['genres'])->toBe([$dnb->id]);
});

it('leaves songs without a genre when the genre is deleted', function () {
    $genre = Genre::findOrCreateNamed('Liquid');
    $song = Song::factory()->for($genre)->create();

    $this->manager->deleteGenre($genre);

    expect($song->fresh()->genre_id)->toBeNull();
});

it('refuses an empty name', function () {
    $this->manager->renameTag(Tag::findOrCreateNamed('subgenre', 'Deep'), '   ');
})->throws(InvalidArgumentException::class);
