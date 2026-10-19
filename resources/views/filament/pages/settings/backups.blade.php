@php
    /** @var bool $available */
    /** @var string $directory */
    /** @var list<array{path: string, name: string, size: int, time: int}> $backups */
    use App\Support\LocalPath;
    use App\Support\PathHtml;
    use Carbon\Carbon;

    $size = fn (int $bytes): string => $bytes >= 1048576 ? number_format($bytes / 1048576, 1, ',', '.').' MB' : number_format($bytes / 1024, 0, ',', '.').' KB';
@endphp

<div class="app-backups">
    @if (! $available)
        <p class="app-empty-line">Las copias automáticas solo funcionan con SQLite. Con otra base de datos, haz las copias con su herramienta (por ejemplo, pg_dump).</p>
    @else
        <p class="app-backups-folder">
            <span>Carpeta</span>
            {!! PathHtml::render(LocalPath::toStored($directory), 'app-path-block') !!}
        </p>

        @if ($backups === [])
            <p class="app-empty-line">Aún no hay copias. Se hará una en la próxima visita, o pulsa «Copia ahora».</p>
        @else
            <ul class="app-backups-list">
                @foreach ($backups as $backup)
                    <li>
                        <span class="app-key">{{ $backup['name'] }}</span>
                        <span class="app-num">{{ Carbon::createFromTimestamp($backup['time'], config('app.timezone'))->locale('es')->isoFormat('D MMM, HH:mm') }}</span>
                        <span class="app-num">{{ $size($backup['size']) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</div>
