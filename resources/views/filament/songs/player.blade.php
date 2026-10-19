@php
    /** @var \App\Models\Song|null $song */
    use App\Services\AudioPreview;
    use App\Support\PathHtml;

    $preview = app(AudioPreview::class);
    $status = $song ? $preview->status($song) : null;
@endphp

@if ($song)
    {{-- wire:ignore keeps the audio playing while the form re-renders; the key starts a new one per song. --}}
    <div class="app-player" wire:key="player-{{ $song->id }}">
        @if ($status === AudioPreview::MISSING)
            <p class="app-player-notice app-warning">No se encuentra el archivo: reubícalo en Escanear.</p>
        @elseif ($status === AudioPreview::NEEDS_FFMPEG)
            <p class="app-player-notice">Configura ffmpeg para escucharlo aquí.</p>
        @else
            <div wire:ignore>
                <audio
                    class="app-audio"
                    controls
                    preload="{{ $status === AudioPreview::NATIVE ? 'metadata' : 'none' }}"
                    src="{{ route('songs.audio', $song) }}"
                    x-data="{ retried: false }"
                    {{-- A browser may know the container but not the codec (ALAC in .m4a): retry through ffmpeg. --}}
                    x-on:error="if (! retried && @js($preview->hasFfmpeg())) { retried = true; $el.src = @js(route('songs.audio', [$song, 'preview' => 1])); $el.load() }"
                ></audio>
            </div>
        @endif

        <div class="app-player-meta">
            {!! PathHtml::render($song->path, 'app-path-block') !!}

            @if ($song->duration)
                <span class="app-num app-player-duration">{{ gmdate($song->duration >= 3600 ? 'G:i:s' : 'i:s', (int) round($song->duration)) }}</span>
            @endif
        </div>
    </div>
@endif
