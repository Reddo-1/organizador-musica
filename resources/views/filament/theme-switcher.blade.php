{{--
    Filament's theme switcher lives in the user menu, and this app has no user.
    Its buttons call close() from the menu's dropdown, so this wrapper provides a no-op one.
--}}
<div x-data="{ close() {} }" class="app-theme-switcher">
    <x-filament-panels::theme-switcher />
</div>
