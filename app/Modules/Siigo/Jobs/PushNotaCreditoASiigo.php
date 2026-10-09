<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Cartera\Models\NotaCredito;
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
 * Sprint 4 · B.1 · Push asíncrono de una NC manual a SIIGO.
 * Mismo patrón que PushRecepcionASiigo/PushVoucherASiigo.
 */
class PushNotaCreditoASiigo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public bool $manual = false;

    public function __construct(public int $notaCreditoId)
    {
        $this->onQueue(config('siigo.queue', 'siigo'));
    }

    public function backoff(): array
    {
        return [
            10 + random_int(0, 5),
            30 + random_int(0, 10),
            60 + random_int(0, 15),
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
            (new WithoutOverlapping("siigo:nc:{$this->notaCreditoId}"))
                ->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $notaCreditoId): void
    {
        $job = new static($notaCreditoId);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoEmisionService $emisor): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] nc={$this->notaCreditoId} · push_auto=off");
            return;
        }

        $nc = NotaCredito::find($this->notaCreditoId);
        if (! $nc) {
            $this->log('omitido', 0, "[not-found] nc={$this->notaCreditoId} · ya no existe");
            return;
        }
        if ($nc->siigo_id) {
            $this->log('omitido', 0, "[idempotente] nc {$nc->numeroCompleto()} ya tiene siigo_id={$nc->siigo_id}");
            return;
        }

        try {
            $t0 = microtime(true);
            $emisor->emitirNotaCredito($nc);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $this->log('exitoso', $ms, "[OK] NC {$nc->numeroCompleto()} → SIIGO {$nc->fresh()->siigo_id}");
        } catch (SiigoRateLimitedException $e) {
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'notas_credito',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] nc={$this->notaCreditoId} · {$e->getMessage()}",
            'detalle' => ['nc_id' => $this->notaCreditoId, 'exception' => class_basename($e), 'trace' => mb_substr($e->getTraceAsString(), 0, 2000)],
            'user_id' => null,
        ]);
        Log::channel('siigo')->error('PushNotaCreditoASiigo agotó reintentos', [
            'nc_id' => $this->notaCreditoId, 'error' => $e->getMessage(),
        ]);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'notas_credito',
            'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0,
            'actualizados' => 0, 'errores' => 0, 'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['nc_id' => $this->notaCreditoId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
