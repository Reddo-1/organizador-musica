@php
    use App\Filament\Pages\Scan;
    use App\Models\Song;
@endphp

<section class="app-panel app-playlist-empty">
    <h2 class="app-panel-title">Playlist</h2>
    @if (Song::query()->exists())
        <p class="app-empty-line">Ajusta los filtros y pulsa «Generar». Saldrá aquí para quitar temas, cambiar el orden arrastrando y guardarla como .m3u8.</p>
    @else
        <p class="app-empty-line">Aún no hay temas en la biblioteca. Escanea tu carpeta de música para darlos de alta y clasificarlos; después podrás generar playlists aquí.</p>
        <div class="app-empty-action">
            <x-filament::button tag="a" :href="Scan::getUrl()" icon="heroicon-o-folder-open">Escanear carpeta</x-filament::button>
        </div>
    @endif
</section>
