@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Song> $songs */
    /** @var int $pending Songs of the queue that still belong to the view. */
    use App\Support\Palette;
@endphp

<section class="app-panel app-queue" aria-label="Cola de temas">
    <header class="app-queue-head">
        <h2 class="app-panel-title">Cola</h2>
        <span class="app-panel-meta app-num">{{ $pending === 1 ? '1 pendiente' : number_format($pending, 0, ',', '.').' pendientes' }} de {{ number_format($songs->count(), 0, ',', '.') }}</span>
    </header>

    @if ($songs->isEmpty())
        <p class="app-empty-line">La cola está vacía.</p>
    @else
        <ol class="app-queue-list">
            @foreach ($songs as $index => $song)
                @php $isCurrent = $song->id === $this->songId; @endphp
                <li wire:key="queue-{{ $song->id }}-{{ $isCurrent ? 'current' : 'rest' }}">
                    <button
                        type="button"
                        wire:click="selectSong({{ $song->id }})"
                        @if ($isCurrent)
                            aria-current="true"
                            x-init="$el.scrollIntoView({ block: 'nearest' })"
                        @endif
                        @class(['app-queue-item', 'is-current' => $isCurrent])
                    >
                        <span class="app-queue-index app-num">{{ $index + 1 }}</span>
                        <span class="app-queue-text">
                            <span class="app-queue-title">{{ $song->title }}</span>
                            <span class="app-queue-artist">{{ $song->artist ?? '—' }}</span>
                        </span>
                        @if ($song->energy)
                            <span class="app-queue-energy fi-color fi-color-{{ Palette::energy($song->energy) }} app-num" title="Energía {{ $song->energy }}">{{ $song->energy }}</span>
                        @endif
                    </button>
                </li>
            @endforeach
        </ol>
    @endif
</section>
