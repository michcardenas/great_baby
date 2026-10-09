<?php

namespace App\Modules\Siigo\Jobs\Middleware;

use App\Modules\Siigo\Exceptions\CredencialSiigoInvalida;
use App\Modules\Siigo\Models\SiigoConfig;
use Illuminate\Support\Facades\Log;

/**
 * Con la credencial de SIIGO muerta, los push esperan en vez de morir.
 *
 * El 2026-10-08 venció la llave y quedaron **1008 trabajos** en `failed_jobs`:
 * 452 productos —el catálogo entero— más asientos, devoluciones y recepciones.
 * Casi todos con `MaxAttemptsExceeded`, que encima **pisa el error original**,
 * así que ni siquiera quedaba escrito que el problema había sido la llave. Y
 * una vez en `failed_jobs` nadie los vuelve a mandar solo.
 *
 * Una credencial vencida no es un fallo de este documento, es una caída del
 * servicio: la respuesta correcta es esperar, no quemar intentos. Acá el job
 * se devuelve a la cola con una espera y se reintenta hasta el plazo que fija
 * cada job en `retryUntil()`. Cuando Aracely pega la llave nueva en
 * `/app/siigo`, la cola arranca sola.
 *
 * Lo que NO hace: tapar errores de verdad. Si SIIGO rechaza el documento —mal
 * formado, cuenta inexistente, producto sin grupo contable— eso sí cuenta
 * como intento y termina en `failed_jobs`, que es donde debe verse.
 */
class EsperarCredencialSiigo
{
    /** Cuánto espera antes de volver a probar, en segundos. */
    public const ESPERA = 300;

    public function handle(object $job, \Closure $next): mixed
    {
        if (! $this->credencialMuerta()) {
            return $next($job);
        }

        Log::channel('siigo')->warning('Push aplazado: la credencial de SIIGO no está autenticando', [
            'job' => $job::class,
            'reintenta_en_seg' => self::ESPERA,
        ]);

        // `release()` sí gasta un intento del contador de la cola, por eso los
        // jobs usan `retryUntil()` en vez de un `tries` pelado: el límite pasa
        // a ser el reloj y no el número de reintentos.
        return $job->release(self::ESPERA);
    }

    private function credencialMuerta(): bool
    {
        try {
            // La regla vive en el modelo porque el aviso de cola atascada de
            // `/app/siigo` tiene que usar exactamente la misma: si difieren,
            // la pantalla manda a prender el worker mientras los jobs están
            // esperando por la llave.
            return SiigoConfig::current()->credencialMuerta();
        } catch (\Throwable) {
            return false;   // ante la duda, que el job corra y falle con su error real
        }
    }

    /** Para que los jobs sepan reconocer el caso sin repetir la regla. */
    public static function esFalloDeCredencial(\Throwable $e): bool
    {
        return $e instanceof CredencialSiigoInvalida;
    }
}
