<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Observers de dominio
        \App\Modules\Cartera\Models\PagoVenta::observe(\App\Modules\Cartera\Observers\PagoVentaObserver::class);

        // Re-audit SEG A2 · rate-limiters compuestos para el Portal B2B.
        //   `portal-login`     → 5 intentos/min por email+IP (bloquea brute-force targeteado)
        //   `portal-login-ip`  → 15 intentos/10min por IP (defensa distribuida contra pools)
        RateLimiter::for('portal-login', function (Request $request) {
            $email = strtolower((string) $request->input('email', $request->input('documento', '')));
            return Limit::perMinute(5)->by($email . '|' . $request->ip());
        });
        RateLimiter::for('portal-login-ip', function (Request $request) {
            return Limit::perMinutes(10, 15)->by($request->ip());
        });
    }
}
