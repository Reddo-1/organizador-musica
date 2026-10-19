@php
    /** @var int $matching */
    /** @var int $total */
    $number = fn (int $value): string => number_format($value, 0, ',', '.');
@endphp

{{-- The answer of the screen: how many songs the filters leave (SPEC §5.7), updated live. --}}
<div class="app-counter" aria-live="polite">
    <p>
        <strong @class(['app-num', 'app-warning' => $matching === 0])>{{ $number($matching) }}</strong>
        de <span class="app-num">{{ $number($total) }}</span> temas cumplen los filtros
    </p>
</div>
