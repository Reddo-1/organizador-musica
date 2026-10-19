<?php

use App\Models\FilterPreset;
use App\Services\FilterPresets;

it('saves, loads, replaces by name and deletes presets', function () {
    $presets = app(FilterPresets::class);

    $preset = $presets->save('Cierre neuro', ['energy' => [4, 5]]);
    $presets->save('CIERRE NEURO', ['energy' => [5, 5]]);

    expect(FilterPreset::count())->toBe(1)
        ->and($presets->load($preset->id))->toBe(['energy' => [5, 5]])
        ->and($presets->options())->toBe([$preset->id => 'CIERRE NEURO']);

    $presets->delete($preset->id);

    expect(FilterPreset::count())->toBe(0);
});

it('needs a name', function () {
    app(FilterPresets::class)->save('  ', []);
})->throws(InvalidArgumentException::class);
