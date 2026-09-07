<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use App\Modules\Dropi\Filament\Pages\DashboardDropi;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('GREAT BABY · ERP')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Admin/Resources'), for: 'App\Filament\Admin\Resources')
            ->discoverResources(in: app_path('Modules/Dropi/Filament/Resources'), for: 'App\Modules\Dropi\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Cartera/Filament/Resources'), for: 'App\Modules\Cartera\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Compras/Filament/Resources'), for: 'App\Modules\Compras\Filament\Resources')
            ->discoverResources(in: app_path('Modules/Inventario/Filament/Resources'), for: 'App\Modules\Inventario\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Admin/Pages'), for: 'App\Filament\Admin\Pages')
            ->discoverPages(in: app_path('Modules/Dropi/Filament/Pages'), for: 'App\Modules\Dropi\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Cartera/Filament/Pages'), for: 'App\Modules\Cartera\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Contabilidad/Filament/Pages'), for: 'App\Modules\Contabilidad\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Siigo/Filament/Pages'), for: 'App\Modules\Siigo\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Compras/Filament/Pages'), for: 'App\Modules\Compras\Filament\Pages')
            ->discoverPages(in: app_path('Modules/Inventario/Filament/Pages'), for: 'App\Modules\Inventario\Filament\Pages')
            ->discoverWidgets(in: app_path('Modules/Dropi/Filament/Widgets'), for: 'App\Modules\Dropi\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Cartera/Filament/Widgets'), for: 'App\Modules\Cartera\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Compras/Filament/Widgets'), for: 'App\Modules\Compras\Filament\Widgets')
            ->discoverWidgets(in: app_path('Modules/Inventario/Filament/Widgets'), for: 'App\Modules\Inventario\Filament\Widgets')
            ->pages([
                DashboardDropi::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Admin/Widgets'), for: 'App\Filament\Admin\Widgets')
            ->widgets([
                // Los widgets Dropi se registran directamente en DashboardDropi::getWidgets().
                AccountWidget::class,
            ])
            ->defaultAvatarProvider(\App\Support\InicialesAvatarProvider::class)
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
            ])
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn (): string => Blade::render('<livewire:bell-notificaciones />'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('<livewire:command-palette />'),
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn (): string => '<button type="button" onclick="window.dispatchEvent(new KeyboardEvent(\'keydown\',{key:\'k\',ctrlKey:true,metaKey:true}))" style="padding:.4rem .7rem;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);border-radius:.4rem;color:#f59e0b;cursor:pointer;font-size:.8rem;display:inline-flex;align-items:center;gap:.4rem;">🔍 <kbd style="padding:.05rem .3rem;background:rgba(0,0,0,.35);border-radius:.2rem;font-family:ui-monospace,monospace;font-size:.7rem;">⌘K</kbd></button>',
            );
    }
}
