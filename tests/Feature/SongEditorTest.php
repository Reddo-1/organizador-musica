<?php

use App\Enums\SetRole;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use App\Services\SongEditor;

beforeEach(fn () => $this->editor = app(SongEditor::class));

it('gives the form state of a song, tags by category', function () {
    $song = Song::factory()->classified(4, SetRole::Weight)->create();
    $song->tags()->attach([
        Tag::findOrCreateNamed('subgenre', 'Neurofunk')->id,
        Tag::findOrCreateNamed('percussion', 'Rolling')->id,
    ]);

    $data = $this->editor->formData($song);

    expect($data['energy'])->toBe(4)
        ->and($data['set_role'])->toBe('weight')
        ->and($data['tags'])->toBe(['subgenre' => ['Neurofunk'], 'percussion' => ['Rolling']]);
});

it('saves the card, creating new tags by name and ignoring their case', function () {
    $song = Song::factory()->create();
    $existing = Tag::findOrCreateNamed('subgenre', 'Halftime');

    $this->editor->save($song, [
        'title' => 'Sombra', 'artist' => ' Kanine ', 'energy' => '5', 'set_role' => 'open',
        'bpm' => 174.5, 'musical_key' => '8A', 'notes' => '',
        'tags' => ['subgenre' => ['halftime', 'Deep'], 'percussion' => []],
    ]);

    $song->refresh();

    expect($song->artist)->toBe('Kanine')
        ->and($song->energy)->toBe(5)
        ->and($song->set_role)->toBe(SetRole::Open)
        ->and($song->notes)->toBeNull()
        ->and($song->tagsIn('subgenre')->pluck('name')->all())->toBe(['Deep', 'Halftime'])
        ->and($song->tags->pluck('id'))->toContain($existing->id)
        ->and(Tag::query()->where('category', 'subgenre')->count())->toBe(2);
});

it('clears energy and role with the "–" option', function () {
    $song = Song::factory()->classified(3, SetRole::Body)->create();

    $this->editor->save($song, ['energy' => 0, 'set_role' => '']);

    expect($song->fresh()->energy)->toBeNull()
        ->and($song->fresh()->set_role)->toBeNull();
});

it('refuses an energy out of 1-5', function () {
    $this->editor->setEnergy(Song::factory()->create()->id, 7);
})->throws(InvalidArgumentException::class);

it('changes energy, role and genre of many songs at once', function () {
    $songs = Song::factory()->count(3)->create();
    $genre = Genre::findOrCreateNamed('Techstep');

    $this->editor->setEnergy($songs->modelKeys(), 2);
    $this->editor->setRole($songs->modelKeys(), SetRole::Breather);
    $this->editor->setGenre($songs->modelKeys(), $genre->id);

    expect(Song::query()->where('energy', 2)->where('set_role', 'breather')->where('genre_id', $genre->id)->count())->toBe(3);
});

it('adds a tag to ten songs at once and removes it again', function () {
    $songs = Song::factory()->count(10)->create();

    expect($this->editor->addTags($songs->modelKeys(), 'percussion', ['Rolling', ' rolling ']))->toBe(10);

    $tag = Tag::query()->where('category', 'percussion')->sole();

    expect($tag->name)->toBe('Rolling')
        ->and($tag->songs()->count())->toBe(10);

    $this->editor->removeTags($songs->take(4)->modelKeys(), [$tag->id]);

    expect($tag->songs()->count())->toBe(6);
});

it('takes songs out of the app without touching their files', function () {
    $path = putTrack(temporaryFolder(), 'Kanine - Sombra.mp3');
    $song = Song::factory()->create(['path' => $path]);

    $this->editor->remove([$song->id]);

    expect(Song::count())->toBe(0)->and(file_exists($path))->toBeTrue();
});
