<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Siigo\Exceptions\SiigoRateLimitedException;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Services\SiigoEmisionService;
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

/**
 * F9 · Push asíncrono de una RECEPCIÓN DE COMPRA (factura de compra al proveedor)
 * hacia SIIGO. Reutiliza el patrón exacto de `PushProductoASiigo`:
 *   - Kill-switch con `SiigoConfig::pushAutoActivo()` (bypass si $manual=true).
 *   - Rate limit con middleware `RateLimited('siigo-api')`.
 *   - `WithoutOverlapping` por recepción.
 *   - Backoff con jitter.
 *   - `SiigoRateLimitedException` → release al worker sin gastar try.
 *   - Log completo en `siigo_sync_log` (recurso='compras').
 */
class PushRecepcionASiigo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 60;

    public bool $manual = false;

    public function __construct(public int $recepcionId)
    {
        $this->onQueue(config('siigo.queue', 'siigo'));
    }

    public function backoff(): array
    {
        return [
            10  + random_int(0, 5),
            30  + random_int(0, 10),
            60  + random_int(0, 15),
            120 + random_int(0, 30),
            300 + random_int(0, 60),
        ];
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
            (new WithoutOverlapping("siigo:recepcion:{$this->recepcionId}"))
                ->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $recepcionId): void
    {
        $job = new static($recepcionId);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoEmisionService $emisor): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] recepcion={$this->recepcionId} · push_auto=off");
            return;
        }

        $recepcion = RecepcionCompra::find($this->recepcionId);
        if (! $recepcion) {
            $this->log('omitido', 0, "[not-found] recepcion={$this->recepcionId} · ya no existe");
            return;
        }

        if ($recepcion->siigo_id) {
            $this->log('omitido', 0, "[idempotente] recepcion {$recepcion->numero} ya tiene siigo_id={$recepcion->siigo_id}");
            return;
        }

        try {
            $t0 = microtime(true);
            $emisor->emitirCompra($recepcion);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $this->log('exitoso', $ms, "[OK] compra {$recepcion->numero} → SIIGO {$recepcion->fresh()->siigo_number}");
        } catch (SiigoRateLimitedException $e) {
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'compras',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] recepcion={$this->recepcionId} · {$e->getMessage()}",
            'detalle' => [
                'recepcion_id' => $this->recepcionId,
                'exception' => class_basename($e),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ],
            'user_id' => null,
        ]);

        Log::channel('siigo')->error('PushRecepcionASiigo agotó reintentos', [
            'recepcion_id' => $this->recepcionId,
            'error' => $e->getMessage(),
        ]);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'compras',
            'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0,
            'actualizados' => 0,
            'errores' => 0,
            'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['recepcion_id' => $this->recepcionId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
