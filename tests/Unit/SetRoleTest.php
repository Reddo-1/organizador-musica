<?php

use App\Enums\SetRole;

it('has the four set roles with their Spanish labels', function () {
    expect(SetRole::options())->toBe([
        'open' => 'Abrir',
        'body' => 'Cuerpo',
        'weight' => 'Peso',
        'breather' => 'Respiro',
    ]);
});

it('gives each role its own colour', function () {
    $colors = array_map(fn (SetRole $role): string => $role->getColor(), SetRole::cases());

    expect($colors)->toHaveCount(4)->and(array_unique($colors))->toHaveCount(4);
});
