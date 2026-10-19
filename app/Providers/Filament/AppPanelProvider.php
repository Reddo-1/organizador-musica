<?php

namespace App\Providers\Filament;

use App\Support\Palette;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AppPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Local, single-user app (SPEC §1): no login, no auth middleware, served at the root.
        return $panel
            ->default()
            ->id('app')
            ->path('')
            ->brandName('Organizador de música')
            ->brandLogo(fn (): View => view('filament.brand'))
            ->brandLogoHeight('1.75rem')
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Violet,
                // True neutral greys whose darkest steps are the dark theme's surfaces:
                // #000 ground, #111 cards, #2a2a2a nested panels.
                'gray' => array_replace(Color::Neutral, [800 => '#2a2a2a', 900 => '#111111', 950 => '#000000']),
                ...Palette::filamentColors(),
            ])
            ->defaultThemeMode(ThemeMode::Dark)
            ->sidebarWidth('15rem')
            // The navigation is flat, so breadcrumbs would only repeat the page title.
            ->breadcrumbs(false)
            ->viteTheme('resources/css/filament/app/theme.css')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            // Without a user there is no user menu, which is where Filament keeps the theme switcher.
            ->renderHook(PanelsRenderHook::TOPBAR_END, fn (): View => view('filament.theme-switcher'))
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ]);
    }
}
