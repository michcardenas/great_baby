<?php

namespace App\Modules\Notificaciones\Jobs;

use App\Modules\Cartera\Models\NotaCredito;
use App\Modules\Cartera\Models\NotaDebito;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Notificaciones\Services\NotificadorCadenaValor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * QA-FIX #5 · Envuelve las llamadas WhatsApp en un Job async para no bloquear
 * requests de Aracely (Http::post timeout 10s dentro de observer/action = feo).
 */
class NotificarCadenaValorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    /**
     * @param string $evento uno de: oc-aprobada, recepcion-confirmada, nc-emitida, nd-emitida
     * @param int    $modelId id de OrdenCompra / RecepcionCompra / NotaCredito / NotaDebito según evento
     */
    public function __construct(public string $evento, public int $modelId)
    {
        $this->onQueue(config('notificaciones.queue', 'default'));
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(NotificadorCadenaValor $noti): void
    {
        try {
            match ($this->evento) {
                'oc-aprobada' => $noti->ocAprobada(OrdenCompra::findOrFail($this->modelId)),
                'recepcion-confirmada' => $noti->recepcionConfirmada(RecepcionCompra::findOrFail($this->modelId)),
                'nc-emitida' => $noti->notaCreditoEmitida(NotaCredito::findOrFail($this->modelId)),
                'nd-emitida' => $noti->notaDebitoEmitida(NotaDebito::findOrFail($this->modelId)),
                default => throw new \InvalidArgumentException("Evento desconocido: {$this->evento}"),
            };
        } catch (\Throwable $e) {
            Log::channel('single')->warning("[WA-Job] {$this->evento}#{$this->modelId} falló: {$e->getMessage()}");
            throw $e; // deja que el retry maneje
        }
    }
}
