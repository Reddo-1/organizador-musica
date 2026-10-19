@php
    use App\Filament\Pages\Scan;
    use App\Services\ClassificationQueue;

    $message = match (true) {
        filled($search) => "Ningún tema de «{$view}» coincide con «{$search}».",
        $this->queueView === ClassificationQueue::NO_ENERGY => 'Todos los temas tienen energía.',
        $this->queueView === ClassificationQueue::NO_PERCUSSION => 'Todos los temas tienen percusión.',
        $this->queueView === ClassificationQueue::NO_ROLE => 'Todos los temas tienen rol.',
        default => 'Aún no hay temas.',
    };
@endphp

<x-filament::empty-state :heading="$message">
    <x-slot name="description">
        @if ($this->queueView === ClassificationQueue::ALL && blank($search))
            Escanea tu carpeta de música para darlos de alta.
        @else
            Cambia de vista para seguir revisando, o mira la biblioteca entera en «Todas».
        @endif
    </x-slot>

    <x-slot name="footer">
        @if ($this->queueView === ClassificationQueue::ALL && blank($search))
            <x-filament::button tag="a" :href="Scan::getUrl()">Escanear carpeta</x-filament::button>
        @else
            <x-filament::button color="gray" wire:click="$set('queueView', 'todas')">Ver todas</x-filament::button>
        @endif
    </x-slot>
</x-filament::empty-state>
