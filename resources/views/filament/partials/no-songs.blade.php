<x-filament::empty-state
    heading="Aún no hay temas"
    description="Escanea tu carpeta de música para darlos de alta. Los archivos solo se leen, nunca se modifican."
>
    <x-slot name="footer">
        <x-filament::button tag="a" :href="\App\Filament\Pages\Scan::getUrl()">
            Escanear carpeta
        </x-filament::button>
    </x-slot>
</x-filament::empty-state>
