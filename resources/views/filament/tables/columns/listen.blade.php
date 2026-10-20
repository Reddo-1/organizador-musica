@php
    /** @var \App\Models\Song $song */
    use App\Services\AudioPreview;

    $song = $getRecord();
    $preview = app(AudioPreview::class);
    $status = $preview->status($song);
    $unavailable = match ($status) {
        AudioPreview::MISSING => 'No se encuentra el archivo',
        AudioPreview::NEEDS_FFMPEG => 'Configura ffmpeg para escucharlo aquí',
        default => null,
    };
@endphp

<div class="app-cell">
    @if ($unavailable)
        <span class="app-listen is-unavailable" title="{{ $unavailable }}" aria-label="{{ $unavailable }}">
            <x-filament::icon icon="heroicon-m-speaker-x-mark" class="app-listen-icon" />
        </span>
    @else
        {{-- Plays in the dock at the bottom of Biblioteca; pressing the playing song again pauses it. --}}
        <button
            type="button"
            class="app-listen"
            x-data
            x-bind:class="{ 'is-playing': $store.appDock && $store.appDock.id === {{ $song->id }} && $store.appDock.playing }"
            x-bind:aria-pressed="($store.appDock && $store.appDock.id === {{ $song->id }} && $store.appDock.playing) ? 'true' : 'false'"
            aria-label="Escuchar {{ $song->displayName() }}"
            title="Escuchar"
            x-on:click.prevent.stop="$dispatch('app-dock-play', @js([
                'id' => $song->id,
                'src' => route('songs.audio', $song),
                'retry' => $preview->hasFfmpeg() ? route('songs.audio', [$song, 'preview' => 1]) : null,
                'label' => $song->displayName(),
            ]))"
        >
            <x-filament::icon icon="heroicon-s-play" class="app-listen-icon app-listen-play" />
            <x-filament::icon icon="heroicon-s-pause" class="app-listen-icon app-listen-pause" />
        </button>
    @endif
</div>
