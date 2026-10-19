@php
    /** @var array{total: int, withEnergy: int, withPercussion: int, missing: int} $stats */
    $share = fn (int $part): string => $stats['total'] > 0 ? number_format(100 * $part / $stats['total'], 0, ',', '.').' %' : '—';
    $number = fn (int $value): string => number_format($value, 0, ',', '.');
@endphp

<section class="app-panel app-metrics" aria-label="Resumen de la biblioteca">
<dl class="app-stats app-stats-flush">
    <div class="app-stat">
        <dt>Temas</dt>
        <dd class="app-num">{{ $number($stats['total']) }}</dd>
    </div>
    <div class="app-stat">
        <dt>Con energía</dt>
        <dd class="app-num">{{ $number($stats['withEnergy']) }} <span class="app-stat-share">{{ $share($stats['withEnergy']) }}</span></dd>
    </div>
    <div class="app-stat">
        <dt>Con percusión</dt>
        <dd class="app-num">{{ $number($stats['withPercussion']) }} <span class="app-stat-share">{{ $share($stats['withPercussion']) }}</span></dd>
    </div>
    <div class="app-stat">
        <dt>No encontrados</dt>
        <dd @class(['app-num', 'app-warning' => $stats['missing'] > 0])>{{ $number($stats['missing']) }}</dd>
    </div>
</dl>
</section>
