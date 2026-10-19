{{--
    Keys (SPEC §5.4): 1-5 set the energy unless the focus is in a text field; Ctrl+Enter saves and
    moves on (bound on the "Guardar y siguiente" button, so it also works while typing).
--}}
<div
    class="app-keys"
    x-data
    x-on:keydown.window="
        if ($event.ctrlKey || $event.metaKey || $event.altKey || $event.repeat) return
        if (! /^[1-5]$/.test($event.key)) return
        const target = $event.target
        if (target.closest('textarea, select, [contenteditable=true], .fi-modal, input:not([type=radio]):not([type=checkbox]):not([type=button])')) return
        $event.preventDefault()
        $wire.setEnergy(Number($event.key))
    "
>
    <span><kbd>1</kbd>–<kbd>5</kbd> energía</span>
    <span><kbd>Ctrl</kbd> + <kbd>Enter</kbd> guardar y siguiente</span>
    <span><kbd>Ctrl</kbd> + <kbd>S</kbd> guardar</span>
</div>
