<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Cartera\Models\NotaDebito;
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

class PushNotaDebitoASiigo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 60;
    public bool $manual = false;

    public function __construct(public int $notaDebitoId)
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

    public function middleware(): array
    {
        return [
            new RateLimited('siigo-api'),
            (new WithoutOverlapping("siigo:nd:{$this->notaDebitoId}"))
                ->releaseAfter(60)->expireAfter(180),
        ];
    }

    public static function dispatchManual(int $notaDebitoId): void
    {
        $job = new static($notaDebitoId);
        $job->manual = true;
        dispatch($job);
    }

    public function handle(SiigoEmisionService $emisor): void
    {
        if (! $this->manual && ! SiigoConfig::pushAutoActivo()) {
            $this->log('omitido', 0, "[kill-switch] nd={$this->notaDebitoId} · push_auto=off");
            return;
        }

        $nd = NotaDebito::find($this->notaDebitoId);
        if (! $nd) {
            $this->log('omitido', 0, "[not-found] nd={$this->notaDebitoId}");
            return;
        }
        if ($nd->siigo_id) {
            $this->log('omitido', 0, "[idempotente] nd {$nd->numeroCompleto()} ya sync");
            return;
        }

        try {
            $t0 = microtime(true);
            $emisor->emitirNotaDebito($nd);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            $this->log('exitoso', $ms, "[OK] ND {$nd->numeroCompleto()} → SIIGO {$nd->fresh()->siigo_id}");
        } catch (SiigoRateLimitedException $e) {
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'notas_debito',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1, 'duracion_ms' => 0,
            'mensaje' => "[job-failed] nd={$this->notaDebitoId} · {$e->getMessage()}",
            'detalle' => ['nd_id' => $this->notaDebitoId, 'exception' => class_basename($e), 'trace' => mb_substr($e->getTraceAsString(), 0, 2000)],
            'user_id' => null,
        ]);
        Log::channel('siigo')->error('PushNotaDebitoASiigo agotó reintentos', [
            'nd_id' => $this->notaDebitoId, 'error' => $e->getMessage(),
        ]);
    }

    private function log(string $estado, int $ms, string $msg): void
    {
        SiigoSyncLog::create([
            'recurso' => 'notas_debito',
            'estado' => $estado,
            'nuevos' => $estado === 'exitoso' ? 1 : 0,
            'actualizados' => 0, 'errores' => 0, 'duracion_ms' => $ms,
            'mensaje' => $msg,
            'detalle' => ['nd_id' => $this->notaDebitoId, 'manual' => $this->manual],
            'user_id' => null,
        ]);
    }
}
