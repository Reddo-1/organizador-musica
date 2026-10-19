<?php

use App\Filament\Pages\Classify;
use App\Models\Song;
use Livewire\Livewire;

beforeEach(function () {
    $this->songs = collect(['A', 'B', 'C', 'D', 'E', 'F'])
        ->map(fn (string $title) => Song::factory()->create(['artist' => 'Kanine', 'title' => $title]));
    Song::factory()->classified(5)->create(['artist' => 'Aaa', 'title' => 'Ya clasificada']);
});

it('opens on the first song without energy, with the progress', function () {
    Livewire::test(Classify::class)
        ->assertSet('songId', $this->songs[0]->id)
        ->assertSee('temas con energía')
        ->assertSeeInOrder(['1', 'de', '7'])
        ->assertSchemaStateSet(['title' => 'A']);
});

it('classifies five songs in a row with 1-5 and Ctrl+Enter', function () {
    $page = Livewire::test(Classify::class);

    foreach ([4, 3, 5, 2, 4] as $index => $energy) {
        $page->assertSet('songId', $this->songs[$index]->id)
            ->call('setEnergy', $energy)
            ->assertSchemaStateSet(['energy' => $energy])
            ->call('saveAndNext');

        expect($this->songs[$index]->fresh()->energy)->toBe($energy);
    }

    $page->assertSet('songId', $this->songs[5]->id)->assertSeeInOrder(['6', 'de', '7']);
});

it('binds Ctrl+Enter to "Guardar y siguiente" and listens to 1-5 outside text fields', function () {
    Livewire::test(Classify::class)
        ->assertSeeHtml('mod-enter')
        ->assertSee('Guardar y siguiente')
        ->assertSeeHtml('$wire.setEnergy(Number($event.key))');
});

it('ignores an energy out of 1-5', function () {
    Livewire::test(Classify::class)->call('setEnergy', 7)->assertSchemaStateSet(['energy' => null]);
});

it('moves back and forth without saving', function () {
    Livewire::test(Classify::class)
        ->call('setEnergy', 3)
        ->call('next')
        ->assertSet('songId', $this->songs[1]->id)
        ->call('previous')
        ->assertSet('songId', $this->songs[0]->id);

    expect($this->songs[0]->fresh()->energy)->toBeNull();
});

it('switches views and searches', function () {
    Livewire::test(Classify::class)
        ->set('queueView', 'todas')
        ->assertSet('songId', Song::query()->where('title', 'Ya clasificada')->value('id'))
        ->tap(fn () => $this->songs[3]->update(['title' => 'Dubplate Zeta']))
        ->set('search', 'zeta')
        ->assertSet('queue', [$this->songs[3]->id])
        ->assertSet('songId', $this->songs[3]->id);
});

it('opens the song given in the address even if it is out of the view', function () {
    $classified = Song::query()->where('title', 'Ya clasificada')->sole();

    Livewire::withQueryParams(['tema' => $classified->id])
        ->test(Classify::class)
        ->assertSet('songId', $classified->id)
        ->call('next')
        ->assertSet('songId', $this->songs[0]->id);
});

it('says when nothing is left in the view', function () {
    Song::query()->update(['energy' => 3]);

    Livewire::test(Classify::class)
        ->assertSet('songId', null)
        ->assertSee('Todos los temas tienen energía.');
});
