@php
    /** @var array<int, string> $options */
@endphp

<div class="app-presets">
    <label class="app-presets-label" for="preset-select">Preset</label>
    <x-filament::input.wrapper class="app-presets-select">
        <x-filament::input.select id="preset-select" wire:model.live="preset">
            <option value="">{{ $options === [] ? 'Aún no hay presets' : 'Elige uno para cargarlo' }}</option>
            @foreach ($options as $id => $name)
                <option value="{{ $id }}">{{ $name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
    {{ $this->savePresetAction }}
    {{-- Rendering an action in Blade does not check its visibility. --}}
    @if ($this->deletePresetAction->isVisible())
        {{ $this->deletePresetAction }}
    @endif
</div>
