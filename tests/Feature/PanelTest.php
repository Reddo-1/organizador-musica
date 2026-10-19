<?php

use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;

it('serves the panel at the root without asking for a login', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Generar playlist');

    $this->get('/login')->assertNotFound();
});

it('opens every section of the navigation', function (string $path, string $title) {
    $this->get($path)->assertOk()->assertSee($title);
})->with([
    ['/', 'Generar playlist'],
    ['/clasificar', 'Clasificar'],
    ['/biblioteca', 'Biblioteca'],
    ['/playlists', 'Playlists'],
    ['/escanear', 'Escanear'],
    ['/etiquetas', 'Etiquetas'],
    ['/ajustes', 'Ajustes'],
]);

it('lists the navigation in the order of the spec', function () {
    $this->get('/')->assertSeeInOrder([
        'Generar playlist', 'Clasificar', 'Biblioteca', 'Playlists', 'Escanear', 'Etiquetas', 'Ajustes',
    ]);
});

it('defaults to dark mode with violet as the primary colour', function () {
    $panel = Filament::getPanel('app');

    expect($panel->getDefaultThemeMode())->toBe(ThemeMode::Dark)
        ->and($panel->getColors()['primary'])->toBe(Color::Violet)
        ->and($panel->hasLogin())->toBeFalse();
});

it('renders the interface in Spanish', function () {
    expect(app()->getLocale())->toBe('es');

    $this->get('/')->assertSee('lang="es"', escape: false);
});
