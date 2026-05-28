<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureRole;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class FacultyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('faculty')
            ->path('faculty')
            ->login()
            ->colors(['primary' => Color::Orange])
            ->discoverResources(in: app_path('Filament/Faculty/Resources'), for: 'App\\Filament\\Faculty\\Resources')
            ->discoverPages(in: app_path('Filament/Faculty/Pages'), for: 'App\\Filament\\Faculty\\Pages')
            ->discoverWidgets(in: app_path('Filament/Faculty/Widgets'), for: 'App\\Filament\\Faculty\\Widgets')
            ->navigationGroups(['My Sessions', 'My Classes', 'Records'])
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
                EnsureRole::class.':faculty',
            ]);
    }
}
