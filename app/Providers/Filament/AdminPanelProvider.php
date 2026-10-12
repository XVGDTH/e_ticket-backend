<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Login;
use App\Filament\Pages\ProfilStaff;
use App\Http\Middleware\ExigerSecuriteCompte;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
// use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\AvatarProviders\BrandAvatarProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->profile(ProfilStaff::class)
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('E-Ticket'),
            ])
            ->colors([
                // 'primary' => Color::Amber,
                    'primary' => config('brand.primary'),
                    'danger'  => config('brand.secondary'),
                     'warning' => config('brand.mid'),
            ])
            ->defaultAvatarProvider(BrandAvatarProvider::class)
            //fichier de couleurs pour le theme filament admin
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->brandName(fn () => auth()->user()?->compagnieActuelle()?->nom ?? 'E-Ticket Admin')
            ->brandLogo(fn () => auth()->user()?->compagnieActuelle()?->logo_url)
            ->brandLogoHeight('2.5rem')
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
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                ExigerSecuriteCompte::class,
            ]);
    }
}