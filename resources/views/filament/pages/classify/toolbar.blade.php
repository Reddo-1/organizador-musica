@php
    /** @var array{done: int, total: int} $progress */
    /** @var array<string, int> $counts */
    use App\Services\ClassificationQueue;

    $share = $progress['total'] > 0 ? $progress['done'] / $progress['total'] : 0;
    $number = fn (int $value): string => number_format($value, 0, ',', '.');
@endphp

<section class="app-panel app-classify-head" aria-label="Progreso y vista">
    <div class="app-progress">
        <p class="app-progress-label">
            <span><strong class="app-num">{{ $number($progress['done']) }}</strong> de <span class="app-num">{{ $number($progress['total']) }}</span> temas con energía</span>
            <span class="app-progress-share app-num">{{ number_format(100 * $share, 0, ',', '.') }} %</span>
        </p>
        <div
            class="app-progress-track"
            role="progressbar"
            aria-label="Temas con energía"
            aria-valuemin="0"
            aria-valuemax="{{ $progress['total'] }}"
            aria-valuenow="{{ $progress['done'] }}"
        >
            <div class="app-progress-fill" style="width: {{ round(100 * $share, 2) }}%"></div>
        </div>
    </div>

    <div class="app-classify-controls">
        <div class="app-segmented" role="group" aria-label="Vista">
            @foreach (ClassificationQueue::VIEWS as $key => $label)
                <button
                    type="button"
                    wire:click="$set('queueView', @js($key))"
                    aria-pressed="{{ $this->queueView === $key ? 'true' : 'false' }}"
                    @class(['app-segmented-item', 'is-active' => $this->queueView === $key])
                >
                    {{ $label }}
                    <span class="app-segmented-count app-num">{{ $number($counts[$key]) }}</span>
                </button>
            @endforeach
        </div>

        <div class="app-classify-search">
            <x-filament::input.wrapper prefix-icon="heroicon-o-magnifying-glass">
                <x-filament::input
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar artista, título o ruta"
                    aria-label="Buscar en la cola"
                />
            </x-filament::input.wrapper>
        </div>
    </div>
</section>
