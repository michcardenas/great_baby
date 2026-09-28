<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Siigo\Exceptions\SiigoRateLimitedException;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Services\SiigoEmisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * F11 · Push asíncrono de un ASIENTO CONTABLE (journal) a SIIGO.
 * Se dispara desde InventarioMovimientoObserver cuando se crea un movimiento
 * con tipo contable (traslado / merma / sobrante / ajuste_toma_fisica).
 *
 * Mismo patrón que PushRecepcionASiigo / PushVoucherASiigo.
 */
class PushAsientoASiigo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;
    public bool $manual = false;

    public function __construct(public int $movimientoId)
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

    public function middleware(): array
    {
        return [
            new RateLimited('siigo-api'),
            (new WithoutOverlapping("siigo:mov:{$this->movimientoId}"))
                ->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $movimientoId): void
    {
        $job = new static($movimientoId);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoEmisionService $emisor): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] mov={$this->movimientoId} · push_auto=off");
            return;
        }

        $mov = InventarioMovimiento::find($this->movimientoId);
        if (! $mov) {
            $this->log('omitido', 0, "[not-found] mov={$this->movimientoId} · ya no existe");
            return;
        }
        if ($mov->siigo_journal_id) {
            $this->log('omitido', 0, "[idempotente] mov {$mov->id} ya tiene siigo_journal_id={$mov->siigo_journal_id}");
            return;
        }

        try {
            $t0 = microtime(true);
            $emisor->emitirAsiento($mov);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $this->log('exitoso', $ms, "[OK] asiento mov={$mov->id} · tipo={$mov->tipo} → journal {$mov->fresh()->siigo_journal_id}");
        } catch (SiigoRateLimitedException $e) {
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'asientos',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] mov={$this->movimientoId} · {$e->getMessage()}",
            'detalle' => [
                'mov_id' => $this->movimientoId,
                'exception' => class_basename($e),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ],
            'user_id' => null,
        ]);
        Log::channel('siigo')->error('PushAsientoASiigo agotó reintentos', [
            'mov_id' => $this->movimientoId, 'error' => $e->getMessage(),
        ]);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'asientos',
            'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0,
            'actualizados' => 0,
            'errores' => 0,
            'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['mov_id' => $this->movimientoId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
