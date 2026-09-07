<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Observers de dominio
        \App\Modules\Cartera\Models\PagoVenta::observe(\App\Modules\Cartera\Observers\PagoVentaObserver::class);
    }
}
