@php
    /** @var \App\Models\Song $song */
    use App\Support\Palette;

    $song = $getRecord();
    $energy = $song->energy;
@endphp

{{-- The energy scale as a meter: click a bar to set it, click the current one to clear it. --}}
<div class="app-cell">
<div
    @class(['app-energy', 'fi-color', 'fi-color-'.Palette::energy($energy) => $energy !== null])
    role="radiogroup"
    aria-label="Energía de {{ $song->displayName() }}"
>
    @foreach (range(1, 5) as $step)
        <button
            type="button"
            role="radio"
            aria-checked="{{ $energy === $step ? 'true' : 'false' }}"
            aria-label="Energía {{ $step }}"
            title="Energía {{ $step }}"
            @class(['app-energy-step', 'is-on' => $energy !== null && $step <= $energy])
            style="--step: {{ $step }}"
            wire:click="setEnergy({{ $song->id }}, {{ $energy === $step ? 'null' : $step }})"
        ></button>
    @endforeach

    <span class="app-energy-value app-num">{{ $energy ?? '—' }}</span>
</div>
</div>
