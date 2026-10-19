<?php

use App\Enums\SetRole;
use App\Models\Song;
use App\Models\Tag;
use App\Services\ClassificationQueue;

beforeEach(fn () => $this->queue = app(ClassificationQueue::class));

it('lists each view in library order', function () {
    $b = Song::factory()->create(['artist' => 'Phibes', 'title' => 'B']);
    $a = Song::factory()->classified(3, SetRole::Body)->create(['artist' => 'Kanine', 'title' => 'A']);
    $c = Song::factory()->create(['artist' => 'Rova', 'title' => 'C', 'energy' => 2]);
    $a->tags()->attach(Tag::findOrCreateNamed('percussion', 'Rolling'));

    expect($this->queue->ids(ClassificationQueue::NO_ENERGY))->toBe([$b->id])
        ->and($this->queue->ids(ClassificationQueue::NO_PERCUSSION))->toBe([$b->id, $c->id])
        ->and($this->queue->ids(ClassificationQueue::NO_ROLE))->toBe([$b->id, $c->id])
        ->and($this->queue->ids(ClassificationQueue::ALL))->toBe([$a->id, $b->id, $c->id]);
});

it('narrows the view by artist, title or path', function () {
    $kanine = Song::factory()->create(['artist' => 'Kanine', 'title' => 'Sundown']);
    Song::factory()->create(['artist' => 'Phibes', 'title' => 'Ruido']);
    $byPath = Song::factory()->create(['artist' => 'Rova', 'title' => 'Niebla', 'path' => 'C:\DNB\Kanine remixes\x.mp3']);

    expect($this->queue->ids(ClassificationQueue::ALL, 'kanine'))->toBe([$kanine->id, $byPath->id])
        ->and($this->queue->counts('kanine'))->toBe(['sin-energia' => 2, 'sin-percusion' => 2, 'sin-rol' => 2, 'todas' => 2]);
});

it('measures progress as songs with energy out of all songs', function () {
    Song::factory()->count(2)->classified()->create();
    Song::factory()->count(3)->create();

    expect($this->queue->progress())->toBe(['done' => 2, 'total' => 5]);
});

it('walks a snapshot of the queue', function () {
    expect(ClassificationQueue::after([4, 7, 9], 7))->toBe(9)
        ->and(ClassificationQueue::after([4, 7, 9], 9))->toBeNull()
        ->and(ClassificationQueue::after([4, 7, 9], 100))->toBe(4)
        ->and(ClassificationQueue::before([4, 7, 9], 7))->toBe(4)
        ->and(ClassificationQueue::before([4, 7, 9], 4))->toBeNull();
});

it('refuses a view that does not exist', function () {
    $this->queue->ids('rara');
})->throws(InvalidArgumentException::class);
