<?php

namespace App\Providers\Filament;

use App\Filament\Faculty\Pages\FacultyPortal;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
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
            ->brandName('SeminaryOS Faculty')
            ->homeUrl(fn (): string => FacultyPortal::getUrl(panel: 'faculty'))
            ->colors(['primary' => Color::Indigo])
            ->pages([FacultyPortal::class])
            ->navigation(false)
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
                'cache.headers:private;no_store',
            ])
            ->authMiddleware([
                Authenticate::class,
            ], isPersistent: true);
    }
}
