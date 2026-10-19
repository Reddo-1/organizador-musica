<?php

use App\Models\Playlist;
use App\Models\Song;
use App\Services\PlaylistGenerator;
use App\Services\SongFilter;
use Random\Engine\Mt19937;
use Random\Randomizer;

function generator(int $seed = 7): PlaylistGenerator
{
    return new PlaylistGenerator(app(SongFilter::class), new Randomizer(new Mt19937($seed)));
}

it('takes a random sample when more songs match than asked for', function () {
    Song::factory()->count(30)->create();

    $first = generator(1)->pick([], 20, 'random');
    $second = generator(2)->pick([], 20, 'random');

    expect($first)->toHaveCount(20)
        ->and(array_unique($first))->toHaveCount(20)
        ->and($first)->not->toBe($second);
});

it('takes every matching song with 0', function () {
    Song::factory()->count(12)->create();

    expect(generator()->pick([], 0, 'random'))->toHaveCount(12);
});

it('orders by energy, with ties in random order and songs without energy last', function () {
    $songs = collect([3, null, 1, 5, 3, null, 1])->map(fn (?int $energy) => Song::factory()->create(['energy' => $energy]));

    $energies = fn (array $ids): array => collect($ids)->map(fn (int $id) => Song::find($id)->energy)->all();

    expect($energies(generator()->pick([], 0, 'energy_asc')))->toBe([1, 1, 3, 3, 5, null, null])
        ->and($energies(generator()->pick([], 0, 'energy_desc')))->toBe([5, 3, 3, 1, 1, null, null]);

    $tieOrders = collect(range(1, 12))->map(fn (int $seed) => implode(',', array_slice(generator($seed)->pick([], 0, 'energy_asc'), 0, 2)))->unique();

    expect($tieOrders->count())->toBeGreaterThan(1);
});

it('orders by song year, discovered year and BPM ascending', function (string $order, string $field) {
    Song::factory()->count(8)->create();
    Song::factory()->create([$field => null]);

    $values = collect(generator()->pick([], 0, $order))->map(fn (int $id) => Song::find($id)->{$field});

    expect($values->last())->toBeNull()
        ->and($values->filter(fn ($v) => $v !== null)->values()->all())->toBe($values->filter(fn ($v) => $v !== null)->sort()->values()->all());
})->with([
    ['song_year_asc', 'song_year'],
    ['discovered_year_asc', 'discovered_year'],
    ['bpm_asc', 'bpm'],
]);

it('creates a draft named after today, and another click gives another combination in the same draft', function () {
    $this->travelTo('2026-10-19 21:00');
    Song::factory()->count(25)->create();

    $draft = generator(1)->generate([], 10, 'random');
    $first = $draft->songs()->pluck('songs.id')->all();

    $again = generator(2)->generate([], 10, 'random', $draft);

    expect($draft->name)->toBe('Set 2026-10-19')
        ->and($again->is($draft))->toBeTrue()
        ->and(Playlist::count())->toBe(1)
        ->and($again->songs()->pluck('songs.id')->all())->not->toBe($first)
        ->and($again->songs()->pluck('playlist_song.position')->all())->toBe(range(1, 10))
        ->and($again->filters['count'])->toBe(10)
        ->and($again->filters['order'])->toBe('random');
});

it('does not repeat the name of a set made the same day', function () {
    $this->travelTo('2026-10-19 21:00');
    Playlist::factory()->create(['name' => 'Set 2026-10-19']);
    Playlist::factory()->create(['name' => 'Set 2026-10-19 (2)']);

    expect(PlaylistGenerator::defaultName())->toBe('Set 2026-10-19 (3)');
});

it('refuses an order that does not exist', function () {
    generator()->pick([], 0, 'por-color');
})->throws(InvalidArgumentException::class);
