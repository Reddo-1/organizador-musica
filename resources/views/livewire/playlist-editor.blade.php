@php
    /** @var \App\Models\Playlist $playlist */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Song> $songs */
    use App\Support\Duration;
    use App\Support\Palette;
    use App\Support\PathHtml;
    use App\Support\Plural;
    use Filament\Support\Icons\Heroicon;

    $count = $songs->count();
@endphp

<section class="app-panel app-playlist" aria-label="Playlist">
    <header class="app-playlist-head">
        <input
            type="text"
            class="app-playlist-name"
            wire:model.blur="name"
            maxlength="120"
            aria-label="Nombre de la playlist"
            spellcheck="false"
        />
        <p class="app-panel-meta">
            <span class="app-num">{{ Plural::of($count, 'tema', 'temas') }}</span>
            <span>·</span>
            <span class="app-num">{{ $total }}</span>
            @if ($unknownDurations > 0)
                <span>({{ Plural::of($unknownDurations, 'tema', 'temas') }} sin duración)</span>
            @endif
            @if ($draft)
                <span>·</span>
                <span>Borrador: «Generar» lo vuelve a llenar hasta que lo guardes</span>
            @endif
        </p>
    </header>

    @if ($count === 0)
        <p class="app-empty-line">La playlist está vacía. Ajusta los filtros y genera otra vez.</p>
    @else
        {{-- The energy curve: one bar per position, in the energy scale (SPEC §5.7, §6). --}}
        <figure class="app-curve" aria-label="Curva de energía por posición">
            <div class="app-curve-plot">
                <div class="app-curve-levels" aria-hidden="true">
                    @foreach ([5, 4, 3, 2, 1] as $level)
                        <span>{{ $level }}</span>
                    @endforeach
                </div>
                <div class="app-curve-bars" style="--count: {{ $count }}">
                    @foreach ($songs as $index => $song)
                        <span
                            @class(['app-curve-bar', 'fi-color', 'fi-color-'.Palette::energy($song->energy) => $song->energy, 'is-empty' => ! $song->energy])
                            style="--level: {{ $song->energy ?? 0 }}"
                            title="{{ $index + 1 }}. {{ $song->displayName() }} · energía {{ $song->energy ?? '—' }}"
                        ></span>
                    @endforeach
                </div>
            </div>
            <figcaption class="app-curve-axis app-num" aria-hidden="true">
                <span>1</span>
                @if ($count > 2)
                    <span>{{ (int) ceil($count / 2) }}</span>
                @endif
                <span>{{ $count }}</span>
            </figcaption>
        </figure>

        {{-- forceFallback drags with pointer events, the same in every browser and on touch screens. --}}
        <ol class="app-setlist" wire:sort="moveSong" wire:sort:config="{ forceFallback: true, fallbackTolerance: 3 }">
            @foreach ($songs as $index => $song)
                <li class="app-setlist-item" wire:sort:item="{{ $song->id }}" wire:key="setlist-{{ $playlist->id }}-{{ $song->id }}">
                    <span class="app-setlist-handle" wire:sort:handle title="Arrastra para cambiar el orden">
                        {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedBars2, size: \Filament\Support\Enums\IconSize::Small) }}
                    </span>
                    <span class="app-setlist-position app-num">{{ $index + 1 }}</span>
                    <span class="app-setlist-text">
                        <span class="app-setlist-title">{{ $song->title }}</span>
                        <span class="app-setlist-artist">{{ $song->artist ?? '—' }}</span>
                    </span>
                    <span class="app-setlist-meta">
                        <span class="app-setlist-energy">
                            @if ($song->energy)
                                <span class="app-queue-energy fi-color fi-color-{{ Palette::energy($song->energy) }} app-num" title="Energía {{ $song->energy }}">{{ $song->energy }}</span>
                            @endif
                        </span>
                        <span class="app-setlist-role">{{ $song->set_role?->getLabel() }}</span>
                        <span class="app-num app-setlist-figure">{{ $song->bpm ? \App\Filament\Resources\Songs\Tables\SongsTable::bpm($song->bpm) : '—' }}</span>
                        <span class="app-key app-setlist-figure">{{ $song->musical_key ?? '—' }}</span>
                        <span class="app-num app-setlist-figure">{{ Duration::clock($song->duration) }}</span>
                    </span>
                    <button
                        type="button"
                        class="app-setlist-remove"
                        wire:sort:ignore
                        wire:click="removeSong({{ $song->id }})"
                        title="Quitar de la playlist"
                        aria-label="Quitar {{ $song->displayName() }} de la playlist"
                    >
                        {{ \Filament\Support\generate_icon_html(Heroicon::OutlinedXMark, size: \Filament\Support\Enums\IconSize::Small) }}
                    </button>
                </li>
            @endforeach
        </ol>
    @endif

    <footer class="app-playlist-foot">
        <div class="app-playlist-actions">
            <x-filament::button wire:click="save" :disabled="$count === 0">Guardar .m3u8</x-filament::button>
            <x-filament::button color="gray" wire:click="download" :disabled="$count === 0">Descargar .m3u8</x-filament::button>
        </div>

        @if ($savedPath)
            <p class="app-playlist-saved">Guardada en {!! PathHtml::render($savedPath) !!}</p>
        @endif

        <p class="app-help">Arrastra el .m3u8 a <em>Playlists</em> en rekordbox (o <em>Archivo → Importar → Importar playlist</em>).</p>
    </footer>
</section>
