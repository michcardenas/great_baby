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
        $this->app->bind(\App\Modules\Siigo\Clients\SiigoClient::class, function () {
            return new \App\Modules\Siigo\Clients\SiigoClient(
                \App\Modules\Siigo\Models\SiigoConfig::current()
            );
        });
    }

    public function boot(): void
    {
        // Observers de dominio
        \App\Modules\Cartera\Models\PagoVenta::observe(\App\Modules\Cartera\Observers\PagoVentaObserver::class);
        // F9 · sync a SIIGO cuando una recepción de compra pasa a 'confirmada'.
        \App\Modules\Compras\Models\RecepcionCompra::observe(\App\Modules\Siigo\Observers\RecepcionCompraObserver::class);
        // F11 · asiento SIIGO por movimientos kardex contables (traslado/merma/sobrante/ajuste).
        \App\Modules\Dropi\Models\InventarioMovimiento::observe(\App\Modules\Siigo\Observers\InventarioMovimientoObserver::class);
        // B.1 · NC manual → SIIGO (skip las de Dropi que tienen flujo propio).
        \App\Modules\Cartera\Models\NotaCredito::observe(\App\Modules\Siigo\Observers\NotaCreditoObserver::class);
        // B.2 · ND manual → SIIGO.
        \App\Modules\Cartera\Models\NotaDebito::observe(\App\Modules\Siigo\Observers\NotaDebitoObserver::class);
        // QA-FIX #2 · Sprint 4 · producto → SIIGO (D2 estaba pendiente en Dropi).
        \App\Modules\Dropi\Models\Producto::observe(\App\Modules\Siigo\Observers\ProductoObserver::class);
        // Sprint SIIGO-LIVE · variante crear/editar/borrar también despacha
        // push del padre. Sin esto, agregar variantes desde el form Vue
        // nunca llegaba a SIIGO (el pivot no activa Observer del padre).
        \App\Modules\Dropi\Models\ProductoVariante::observe(\App\Modules\Siigo\Observers\ProductoVarianteObserver::class);
        // PROD-4 · precios por lista disparan push al padre · antes de esto
        // el badge marcaba "sincronizado" aunque SIIGO tuviera valores viejos.
        \App\Modules\Catalogo\Models\PrecioVariante::observe(\App\Modules\Siigo\Observers\PrecioVarianteObserver::class);
        // QA-FIX #7 · pago proveedor → SIIGO voucher egreso.
        \App\Modules\Cartera\Models\PagoProveedor::observe(\App\Modules\Siigo\Observers\PagoProveedorObserver::class);

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

        // SIIGO API · respeta el rate limit por empresa (100 req/min en prod,
        //   10 req/min en cuenta de pruebas). Usado por PushProductoASiigo::middleware.
        //   Cuando se excede, el job se libera con delay = tiempo hasta el próximo
        //   slot disponible (Laravel calcula esto automáticamente).
        RateLimiter::for('siigo-api', function () {
            return Limit::perMinute((int) config('siigo.rate_limit_per_min', 100));
        });
    }
}
