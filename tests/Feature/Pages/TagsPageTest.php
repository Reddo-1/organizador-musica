<?php

use App\Filament\Resources\Songs\Pages\ListSongs;
use App\Livewire\TagsTable;
use App\Models\Genre;
use App\Models\Song;
use App\Models\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('shows a tab per tag category and genres, with the number of songs', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'Halftime');
    Song::factory()->count(3)->create()->each(fn (Song $song) => $song->tags()->attach($tag));

    $this->get('/etiquetas')->assertOk()->assertSeeInOrder(['Subgénero', 'Percusión', 'Géneros']);

    Livewire::test(TagsTable::class, ['category' => 'subgenre'])
        ->assertCanSeeTableRecords([$tag])
        ->assertSee('Halftime')
        ->assertSee('3');
});

it('renames "Halftime" to "Half-time" and then merges it with another', function () {
    $halftime = Tag::findOrCreateNamed('subgenre', 'Halftime');
    $deep = Tag::findOrCreateNamed('subgenre', 'Deep');
    [$a, $b] = Song::factory()->count(2)->create();
    $a->tags()->attach($halftime);
    $b->tags()->attach($deep);

    $table = Livewire::test(TagsTable::class, ['category' => 'subgenre']);

    $table->callAction(TestAction::make('rename')->table($halftime), data: ['name' => 'Half-time'])
        ->assertNotified('Renombrado a «Half-time»');

    expect($halftime->fresh()->name)->toBe('Half-time');

    $table->callAction(TestAction::make('rename')->table($halftime->fresh()), data: ['name' => 'deep'])
        ->assertNotified('Fusionado con «Deep»');

    expect(Tag::find($halftime->id))->toBeNull()
        ->and($deep->songs()->pluck('songs.id')->sort()->values()->all())->toBe([$a->id, $b->id]);
});

it('changes only the case of a tag', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'halftime');

    Livewire::test(TagsTable::class, ['category' => 'subgenre'])
        ->callAction(TestAction::make('rename')->table($tag), data: ['name' => 'Halftime'])
        ->assertNotified('Renombrado a «Halftime»');

    expect($tag->fresh()->name)->toBe('Halftime');
});

it('creates and deletes tags', function () {
    $table = Livewire::test(TagsTable::class, ['category' => 'percussion']);

    $table->callAction(TestAction::make('create')->table(), data: ['name' => 'Rolling'])->assertNotified('Creado');
    $tag = Tag::query()->where('category', 'percussion')->sole();

    $table->callAction(TestAction::make('delete')->table($tag))->assertNotified('Borrado');

    expect(Tag::count())->toBe(0);
});

it('manages genres in their own tab', function () {
    $typo = Genre::findOrCreateNamed('Drum & Bass');
    $dnb = Genre::findOrCreateNamed('Drum and Bass');
    $song = Song::factory()->for($typo)->create();

    Livewire::test(TagsTable::class, ['category' => TagsTable::GENRES])
        ->assertCanSeeTableRecords([$typo, $dnb])
        ->callAction(TestAction::make('rename')->table($typo), data: ['name' => 'Drum and Bass'])
        ->assertNotified('Fusionado con «Drum and Bass»');

    expect($song->fresh()->genre_id)->toBe($dnb->id);
});

it('requires a name', function () {
    Livewire::test(TagsTable::class, ['category' => 'subgenre'])
        ->callAction(TestAction::make('create')->table(), data: ['name' => ''])
        ->assertHasActionErrors(['name' => 'required']);
});

it('links the number of songs to Biblioteca filtered by the tag or the genre', function () {
    $tag = Tag::findOrCreateNamed('subgenre', 'Halftime');
    [$tagged, $other] = Song::factory()->count(2)->create();
    $tagged->tags()->attach($tag);

    $table = Livewire::test(TagsTable::class, ['category' => 'subgenre']);
    $url = $table->instance()->libraryUrl($tag);

    expect(urldecode($url))->toEndWith("/biblioteca?filters[tags_subgenre][values][0]={$tag->id}");
    $table->assertSeeHtml(e($url));

    Livewire::withQueryParams(['filters' => ['tags_subgenre' => ['values' => [$tag->id]]]])
        ->test(ListSongs::class)
        ->assertCanSeeTableRecords([$tagged])
        ->assertCanNotSeeTableRecords([$other]);

    $genre = Genre::query()->create(['name' => 'Drum and Bass']);
    $tagged->update(['genre_id' => $genre->id]);

    Livewire::withQueryParams(['filters' => ['genre_id' => ['values' => [$genre->id]]]])
        ->test(ListSongs::class)
        ->assertCanSeeTableRecords([$tagged])
        ->assertCanNotSeeTableRecords([$other]);
});
