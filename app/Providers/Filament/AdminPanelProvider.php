<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $cssVersion = @filemtime(public_path('css/uaa-admin.css')) ?: 1;

        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('United Arab Agencies')
            ->brandLogo(asset('images/logo.webp'))
            ->darkModeBrandLogo(asset('images/logo.webp'))
            ->brandLogoHeight('2.6rem')
            ->favicon(asset('images/logo.webp'))
            ->defaultThemeMode(ThemeMode::Dark)
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                // UAA premium gold, matching the website's --gold. Color::hex() generates the 50-950 scale.
                'primary' => Color::hex('#C9A227'),
                'success' => Color::hex('#2FB8A6'),
                'warning' => Color::hex('#E0A92F'),
                'danger' => Color::hex('#E0736B'),
                'info' => Color::hex('#5B8DEF'),
                'gray' => Color::hex('#8A8577'),
            ])
            ->navigationGroups([
                NavigationGroup::make('CRM & Leads'),
                NavigationGroup::make('Customer Care'),
                NavigationGroup::make('Properties'),
                NavigationGroup::make('Content'),
                NavigationGroup::make('Careers')->collapsed(),
                NavigationGroup::make('Events')->collapsed(),
                NavigationGroup::make('System')->collapsed(),
            ])
            // Premium black & gold look: fonts + stylesheet (public/css/uaa-admin.css, no build step needed).
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): string => new HtmlString(
                '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
                .'<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">'
                .'<link rel="stylesheet" href="'.e(asset('css/uaa-admin.css')).'?v='.$cssVersion.'">'
            ))
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, fn (): string => new HtmlString(
                '<div class="uaa-login-note">Admin &amp; control room</div>'
            ))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
