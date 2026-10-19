@php
    /** @var array{result: \App\Data\ScanResult, at: \Carbon\CarbonImmutable}|null $lastScan */
    use App\Support\PathHtml;

    $result = $lastScan['result'] ?? null;
@endphp

<section class="app-panel" aria-labelledby="last-scan-heading">
    <header class="app-panel-header">
        <h2 id="last-scan-heading" class="app-panel-title">Último escaneo</h2>

        @if ($result)
            <p class="app-panel-meta">
                <span class="app-nowrap">{{ $lastScan['at']->locale('es')->isoFormat('D [de] MMMM, HH:mm') }} ·</span>
                {!! PathHtml::render($result->folder) !!}
            </p>
        @endif
    </header>

    @if (! $result)
        <p class="app-empty-line">Aún no se ha escaneado la carpeta. El primer escaneo lee las etiquetas de cada archivo y puede tardar unos minutos.</p>
    @else
        <dl class="app-stats">
            <div class="app-stat">
                <dt>Archivos en la carpeta</dt>
                <dd class="app-num">{{ number_format($result->filesInFolder, 0, ',', '.') }}</dd>
            </div>
            <div class="app-stat">
                <dt>Nuevos</dt>
                <dd class="app-num">{{ count($result->new) }}</dd>
            </div>
            <div class="app-stat">
                <dt>Reubicados</dt>
                <dd class="app-num">{{ count($result->relocated) }}</dd>
            </div>
            <div class="app-stat">
                <dt>No encontrados</dt>
                <dd @class(['app-num', 'app-warning' => count($result->missing) > 0])>{{ count($result->missing) }}</dd>
            </div>
            <div class="app-stat">
                <dt>Errores</dt>
                <dd @class(['app-num', 'app-negative' => count($result->errors) > 0])>{{ count($result->errors) }}</dd>
            </div>
        </dl>

        @if ($result->relocated !== [])
            <div class="app-panel-block">
                <h3 class="app-block-title">Reubicados <span class="app-block-meta">ruta vieja → ruta nueva</span></h3>
                <ul class="app-list">
                    @foreach ($result->relocated as $move)
                        <li class="app-move">
                            {!! PathHtml::render($move['from']) !!}
                            <span class="app-move-arrow" aria-label="pasa a">→</span>
                            {!! PathHtml::render($move['to']) !!}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($result->errors !== [])
            <div class="app-panel-block">
                <h3 class="app-block-title">Errores <span class="app-block-meta">estos archivos no se pudieron leer y no se han dado de alta</span></h3>
                <ul class="app-list">
                    @foreach ($result->errors as $error)
                        <li class="app-error-item">
                            {!! PathHtml::render($error['path']) !!}
                            <span class="app-error-message">{{ $error['message'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($result->new !== [])
            <details class="app-panel-block app-disclosure">
                <summary class="app-block-title">{{ count($result->new) }} {{ count($result->new) === 1 ? 'tema nuevo' : 'temas nuevos' }}</summary>
                <ul class="app-list">
                    @foreach ($result->new as $path)
                        <li>{!! PathHtml::render($path) !!}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</section>
