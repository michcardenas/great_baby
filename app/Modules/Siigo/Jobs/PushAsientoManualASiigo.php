<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Contabilidad\Models\AsientoManual;
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

class PushAsientoManualASiigo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public bool $manual = false;

    public function __construct(public int $asientoId)
    {
        $this->onQueue(config('siigo.queue', 'siigo'));
    }

    public function backoff(): array
    {
        return [10 + random_int(0, 5), 30 + random_int(0, 10), 60 + random_int(0, 15), 120 + random_int(0, 30), 300 + random_int(0, 60)];
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
            (new WithoutOverlapping("siigo:asiento-manual:{$this->asientoId}"))->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $asientoId): void
    {
        $job = new static($asientoId);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoEmisionService $emisor): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] asiento={$this->asientoId}");
            return;
        }

        // QA-FIX #10 · lockForUpdate para prevenir dobles POST concurrentes
        // (WithoutOverlapping cubre misma queue worker; procesos separados podrían
        // pasar el guard antes de que uno persista siigo_journal_id).
        \Illuminate\Support\Facades\DB::transaction(function () use ($emisor) {
            $a = AsientoManual::whereKey($this->asientoId)->lockForUpdate()->first();
            if (! $a) { $this->log('omitido', 0, "[not-found] asiento={$this->asientoId}"); return; }
            if ($a->siigo_journal_id) { $this->log('omitido', 0, "[idempotente] asiento #{$a->id} ya sync"); return; }
            if ($a->estado !== 'aprobado') { $this->log('omitido', 0, "[no-aprobado] asiento #{$a->id} estado={$a->estado}"); return; }

            try {
                $t0 = microtime(true);
                $emisor->emitirAsientoManual($a);
                $ms = (int) round((microtime(true) - $t0) * 1000);
                $this->log('exitoso', $ms, "[OK] Asiento #{$a->id} → SIIGO {$a->fresh()->siigo_journal_id}");
            } catch (SiigoRateLimitedException $e) {
                $this->release($e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2))));
            }
        });
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'asientos_manuales', 'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] asiento={$this->asientoId} · {$e->getMessage()}",
            'detalle' => ['asiento_id' => $this->asientoId, 'exception' => class_basename($e)],
            'user_id' => null,
        ]);
        Log::channel('siigo')->error('PushAsientoManualASiigo agotó reintentos', ['asiento_id' => $this->asientoId, 'error' => $e->getMessage()]);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'asientos_manuales', 'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0, 'actualizados' => 0, 'errores' => 0, 'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['asiento_id' => $this->asientoId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
