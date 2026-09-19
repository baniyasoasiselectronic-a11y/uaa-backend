<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
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
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('United Arab Agencies')
            ->brandLogo(asset('images/logo.webp'))
            ->darkModeBrandLogo(asset('images/logo.webp'))
            ->brandLogoHeight('2.75rem')
            ->favicon(asset('images/logo.webp'))
            ->defaultThemeMode(ThemeMode::Dark)
            ->colors([
                // UAA premium gold, matching the frontend's --gold. Color::hex()
                // auto-generates the full 50-950 shade scale Filament needs.
                'primary' => Color::hex('#C9A227'),
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => new HtmlString(<<<'HTML'
                    <style>
                        /* UAA black + gold admin theme */
                        .dark {
                            --gray-50: 243 237 225;
                            --gray-950: 11 11 11;
                        }
                        .dark body,
                        .dark .fi-body {
                            background-color: #0B0B0B;
                        }
                        .dark .fi-sidebar {
                            background-color: #101010;
                            border-right: 1px solid rgba(201, 162, 39, .12);
                        }
                        .dark .fi-topbar {
                            background-color: #101010;
                            border-bottom: 1px solid rgba(201, 162, 39, .12);
                        }
                        .dark .fi-sidebar-item-active .fi-sidebar-item-button {
                            background-color: rgba(201, 162, 39, .12);
                        }
                        .dark .fi-sidebar-item-active .fi-sidebar-item-label {
                            color: #C9A227;
                        }
                        .fi-logo {
                            display: flex;
                            align-items: center;
                            gap: .5rem;
                        }
                    </style>
                    HTML
                ),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                Widgets\FilamentInfoWidget::class,
            ])
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
