<x-filament-panels::page>
    <x-filament::empty-state
        heading="Aún no has generado ninguna playlist"
        description="Las playlists que generes y exportes aparecerán aquí para reabrirlas o volver a exportarlas."
    >
        <x-slot name="footer">
            <x-filament::button tag="a" color="gray" :href="\App\Filament\Pages\GeneratePlaylist::getUrl()">
                Generar playlist
            </x-filament::button>
        </x-slot>
    </x-filament::empty-state>
</x-filament-panels::page>
