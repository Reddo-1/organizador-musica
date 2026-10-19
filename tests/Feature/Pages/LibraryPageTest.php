<?php

use App\Enums\SetRole;
use App\Filament\Resources\Songs\Pages\EditSong;
use App\Filament\Resources\Songs\Pages\ListSongs;
use App\Models\Song;
use App\Models\Tag;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

it('lists the library with its figures', function () {
    $songs = Song::factory()->count(3)->classified()->create();
    Song::factory()->missing()->create();
    $songs->first()->tags()->attach(Tag::findOrCreateNamed('percussion', 'Rolling'));

    Livewire::test(ListSongs::class)
        ->assertCanSeeTableRecords($songs)
        ->assertSeeInOrder(['Temas', '4', 'Con energía', '3', 'Con percusión', '1', 'No encontrados', '1']);
});

it('edits energy and role right in the table', function () {
    $song = Song::factory()->create();

    Livewire::test(ListSongs::class)
        ->call('setEnergy', $song->id, 4)
        ->call('setRole', $song->id, 'weight');

    expect($song->fresh()->energy)->toBe(4)->and($song->fresh()->set_role)->toBe(SetRole::Weight);

    Livewire::test(ListSongs::class)->call('setEnergy', $song->id, null)->call('setRole', $song->id, '');

    expect($song->fresh()->energy)->toBeNull()->and($song->fresh()->set_role)->toBeNull();
});

it('ignores values out of range sent from the browser', function () {
    $song = Song::factory()->classified(2, SetRole::Body)->create();

    Livewire::test(ListSongs::class)->call('setEnergy', $song->id, 9)->call('setRole', $song->id, 'boss');

    expect($song->fresh()->energy)->toBe(2)->and($song->fresh()->set_role)->toBe(SetRole::Body);
});

it('adds a tag to ten songs at once from the bulk actions', function () {
    $songs = Song::factory()->count(10)->create();

    Livewire::test(ListSongs::class)
        ->selectTableRecords($songs->modelKeys())
        ->callAction(TestAction::make('addTags')->table()->bulk(), data: ['category' => 'percussion', 'names' => ['Rolling']])
        ->assertNotified('10 temas con etiquetas añadidas');

    expect(Tag::query()->where('name', 'Rolling')->sole()->songs()->count())->toBe(10);
});

it('sets energy for the selected songs', function () {
    $songs = Song::factory()->count(3)->create();

    Livewire::test(ListSongs::class)
        ->selectTableRecords($songs->modelKeys())
        ->callAction(TestAction::make('setEnergy')->table()->bulk(), data: ['energy' => 5]);

    expect(Song::query()->where('energy', 5)->count())->toBe(3);
});

it('filters by energy, including songs without it', function () {
    $low = Song::factory()->classified(1)->create();
    $high = Song::factory()->classified(5)->create();
    $none = Song::factory()->create();

    Livewire::test(ListSongs::class)
        ->filterTable('energy', ['5', 'none'])
        ->assertCanSeeTableRecords([$high, $none])
        ->assertCanNotSeeTableRecords([$low]);
});

it('saves the full card with the same form as Clasificar', function () {
    $song = Song::factory()->create();

    Livewire::test(EditSong::class, ['record' => $song->getRouteKey()])
        ->fillForm(['title' => 'Bajo Cero', 'energy' => 3, 'set_role' => 'breather', 'tags' => ['subgenre' => ['Deep'], 'percussion' => ['Stepper']]])
        ->call('save')
        ->assertHasNoFormErrors();

    $song->refresh();

    expect($song->title)->toBe('Bajo Cero')
        ->and($song->energy)->toBe(3)
        ->and($song->set_role)->toBe(SetRole::Breather)
        ->and($song->tags->pluck('name')->sort()->values()->all())->toBe(['Deep', 'Stepper']);
});

it('validates the card', function () {
    $song = Song::factory()->create();

    Livewire::test(EditSong::class, ['record' => $song->getRouteKey()])
        ->fillForm(['title' => '', 'bpm' => 400, 'song_year' => 1900])
        ->call('save')
        ->assertHasFormErrors(['title' => 'required', 'bpm' => 'max', 'song_year' => 'min']);
});
