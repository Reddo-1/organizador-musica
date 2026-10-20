@php
    /** @var list<array{name: string, path: string}> $folders */
    /** @var bool $hasParent */
    use App\Support\PathHtml;
@endphp

<div class="app-picker">
    <div class="app-picker-bar">
        <button type="button" class="app-picker-up" wire:click="up" @disabled(! $hasParent) aria-label="Subir a la carpeta de arriba" title="Subir">
            <x-filament::icon icon="heroicon-m-arrow-up" class="app-listen-icon" />
        </button>
        <p class="app-picker-path">
            @if ($path === null)
                Unidades de este PC
            @else
                {{-- Truncated in the middle: the drive and the last folder are what tell where you are. --}}
                {!! PathHtml::render($path, 'app-path-block') !!}
            @endif
        </p>
        @if ($hasParent)
            <button type="button" class="app-picker-drives" wire:click="drives">Unidades</button>
        @endif
    </div>

    <ul class="app-picker-list" wire:loading.class="app-picker-loading">
        @forelse ($folders as $folder)
            <li wire:key="folder-{{ md5($folder['path']) }}">
                <button type="button" class="app-picker-item" wire:click="open(@js($folder['path']))">
                    <x-filament::icon :icon="$hasParent ? 'heroicon-o-folder' : 'heroicon-o-server'" class="app-picker-icon" />
                    <span class="app-picker-name">{{ $folder['name'] }}</span>
                    <x-filament::icon icon="heroicon-m-chevron-right" class="app-picker-chevron" />
                </button>
            </li>
        @empty
            <li class="app-empty-line">{{ $hasParent ? 'Esta carpeta no tiene subcarpetas.' : 'No se encuentra ninguna unidad.' }}</li>
        @endforelse
    </ul>

    <div class="app-picker-foot">
        <x-filament::button wire:click="choose" :disabled="! $hasParent" icon="heroicon-o-check">
            Elegir esta carpeta
        </x-filament::button>
        <span class="app-picker-hint">Entra con un clic en la carpeta y pulsa «Elegir esta carpeta».</span>
    </div>
</div>
