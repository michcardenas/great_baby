<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Exceptions\SiigoRateLimitedException;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use DateTimeInterface;
use App\Modules\Siigo\Jobs\Middleware\EsperarCredencialSiigo;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * UBIC-9 · Push de ubicación local a SIIGO como warehouse.
 *
 * ⚠ DESCONECTADO el 2026-10-09 · nadie lo encola, y no conviene volver a
 * enchufarlo sin leer esto.
 *
 * `/v1/warehouses` es un catálogo de **sólo lectura**. El GET responde 200
 * —de ahí salieron las 43 bodegas cacheadas en `siigo_catalogos`— y el POST
 * responde `404 Resource not found`. No era la credencial: un 401 se ve
 * distinto, y la evidencia es que ninguna ubicación propia del ERP llegó
 * nunca a tener `siigo_id`; las únicas que lo tienen son las `SIIGO-xx` que
 * creó `SiigoService::mapearWarehouses()` trayéndolas de allá.
 *
 * Tampoco era deseable aunque funcionara: se disparaba para toda ubicación
 * activa, y la mayoría son racks, niveles, zonas de avería y reservas
 * —granularidad interna— que no tienen por qué existir como bodegas en la
 * contabilidad.
 *
 * El camino real: la bodega se crea en SIIGO, se trae con «Sincronizar
 * catálogos» y se enlaza desde el selector «Bodega SIIGO» del formulario de
 * ubicaciones. El `PUT` de actualización nunca se pudo comprobar (la
 * credencial estaba caída), así que tampoco se da por bueno.
 *
 * Se deja el archivo porque este repo no tiene git.
 *
 * - Si `siigo_id` es null → POST /v1/warehouses (crea) y guarda el id.
 * - Si `siigo_id` existe  → PUT /v1/warehouses/{id} (actualiza nombre/código).
 *
 * Respeta el kill-switch global (FEATURE_SIIGO_PUSH_AUTO) y permite
 * invocación manual desde el CRUD (`dispatchManual()`).
 */
class PushUbicacionASiigo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public bool $manual = false;

    public function __construct(public int $ubicacionId)
    {
        $this->onQueue(config('siigo.queue', 'siigo'));
    }

    public function backoff(): array
    {
        return [10, 30, 60, 120, 300];
    }

    /**
     * El techo es el reloj, no el contador de intentos.
     *
     * Con `tries = 5` pelado, una credencial vencida mandaba el documento a
     * `failed_jobs` en minutos y de ahi no sale solo: el 2026-10-08 fueron
     * 1008 trabajos, el catalogo entero. `EsperarCredencialSiigo` lo devuelve
     * a la cola mientras la llave este muerta, y `release()` gasta intento,
     * asi que el limite tiene que ser temporal. 12 horas alcanzan para que
     * alguien pegue la llave nueva en /app/siigo sin perder la cola.
     *
     * Los rechazos de verdad siguen acotados por `$maxExceptions`.
     */
    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(12);
    }

    /** Un rechazo real de SIIGO corta a los 5, como antes. */
    public int $maxExceptions = 5;

    public function middleware(): array
    {
        return [
            // Primero de todos: con la credencial muerta no tiene sentido ni
            // pedir turno al rate limiter.
            new EsperarCredencialSiigo(),
            new RateLimited('siigo-api'),
            (new WithoutOverlapping("siigo:ubic:{$this->ubicacionId}"))->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $id): void
    {
        $job = new static($id);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoClient $client): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] ubic={$this->ubicacionId}");
            return;
        }

        $u = InventarioUbicacion::find($this->ubicacionId);
        if (! $u) { $this->log('omitido', 0, "[not-found] ubic={$this->ubicacionId}"); return; }
        if (! $u->activa) { $this->log('omitido', 0, "[inactiva] ubic {$u->codigo}"); return; }

        // SIIGO /v1/warehouses sólo recibe name + code; dirección/ciudad son
        // propias del ERP y no se replican (SIIGO no las guarda a este nivel).
        $payload = [
            'name' => (string) ($u->nombre ?: $u->codigo),
            'code' => (string) $u->codigo,
        ];

        try {
            $t0 = microtime(true);

            if ($u->siigo_id) {
                // UPDATE
                $resp = $client->request('PUT', "/v1/warehouses/{$u->siigo_id}", $payload);
                if (! $resp->ok()) {
                    $this->abortarSegunStatus($u, $resp, 'PUT');
                }
                $u->forceFill(['siigo_sync_at' => now()])->save();
                $ms = (int) round((microtime(true) - $t0) * 1000);
                $this->log('exitoso', $ms, "[OK upd] ubic {$u->codigo} → warehouse {$u->siigo_id}");
            } else {
                // CREATE
                $resp = $client->request('POST', '/v1/warehouses', $payload);
                if (! $resp->ok()) {
                    $this->abortarSegunStatus($u, $resp, 'POST');
                }
                $siigoId = $resp->json('id');
                if (! $siigoId) {
                    throw new RuntimeException("SIIGO devolvió warehouse sin id · body: ".mb_substr((string) $resp->body(), 0, 400));
                }
                $u->forceFill([
                    'siigo_id' => (int) $siigoId,
                    'siigo_sync_at' => now(),
                ])->save();
                $ms = (int) round((microtime(true) - $t0) * 1000);
                $this->log('exitoso', $ms, "[OK new] ubic {$u->codigo} → warehouse {$siigoId}");
            }
        } catch (SiigoRateLimitedException $e) {
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'ubicaciones',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] ubic={$this->ubicacionId} · {$e->getMessage()}",
            'detalle' => ['ubicacion_id' => $this->ubicacionId, 'exception' => class_basename($e)],
            'user_id' => null,
        ]);
        Log::channel('siigo')->error('PushUbicacionASiigo agotó reintentos', [
            'ubicacion_id' => $this->ubicacionId, 'error' => $e->getMessage(),
        ]);
    }

    /**
     * FIX-S0 · si SIIGO responde 4xx (no 429), falla inmediatamente para no
     * reintentar un error de datos cinco veces. 5xx y timeout siguen reintentando.
     */
    private function abortarSegunStatus(InventarioUbicacion $u, $resp, string $verbo): void
    {
        $status = $resp->status();
        $body = mb_substr((string) $resp->body(), 0, 400);
        $msg = "SIIGO rechazó {$verbo} warehouse: HTTP {$status} · {$body}";
        if ($status >= 400 && $status < 500 && $status !== 429) {
            $u->forceFill(['siigo_last_error' => $msg, 'siigo_sync_at' => now()])->save();
            $this->fail(new RuntimeException($msg));
            return;
        }
        throw new RuntimeException($msg);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'ubicaciones',
            'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0, 'actualizados' => 0,
            'errores' => 0, 'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['ubicacion_id' => $this->ubicacionId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
