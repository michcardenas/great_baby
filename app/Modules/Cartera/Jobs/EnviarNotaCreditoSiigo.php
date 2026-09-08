<?php

namespace App\Modules\Cartera\Jobs;

use App\Modules\Dropi\Models\DropiDevolucion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as QueueableTrait;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Encola envío de Nota Crédito a SIIGO.
 * Placeholder — el body SIIGO se llena cuando tengamos las creds (Aracely aún en migración con SIIGO).
 * Va a la cola 'default' porque no hay worker dedicado 'siigo' configurado.
 */
class EnviarNotaCreditoSiigo implements ShouldQueue
{
    use QueueableTrait, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public int $devolucionId, public ?string $refInterna = null) {}

    public function handle(): void
    {
        // Re-chequear reglas al ejecutar — Aracely puede haber apagado la auto-NC
        // después del dispatch. En ese caso resetear el flag para no dejar mintiendo.
        if (! (bool) setting('dropi.auto_nota_credito', true)
            || ! (bool) setting('dropi.auto_nc_envia_siigo', true)) {
            Log::info('[NC-SIIGO] regla desactivada al ejecutar, aborto', ['dev' => $this->devolucionId]);
            $dev = DropiDevolucion::withTrashed()->find($this->devolucionId);
            if ($dev && $dev->genero_nota_credito) {
                $dev->update([
                    'genero_nota_credito' => false,
                    'notas' => trim(($dev->notas ?? '') . "\n[NC skip] regla off al ejecutar job"),
                ]);
            }
            return;
        }

        $dev = DropiDevolucion::with('pedido')->find($this->devolucionId);
        if (! $dev) return;

        // TODO: reemplazar por SiigoService::emitirNotaCredito($dev) cuando estén las creds
        Log::info('[NC-SIIGO] (mock) enviando NC', [
            'devolucion' => $dev->id,
            'guia' => $dev->pedido?->guia,
            'ref' => $this->refInterna ?? $dev->nota_credito_ari_id,
        ]);
    }

    /**
     * Se ejecuta cuando el job falla definitivamente (después de $tries).
     * Revierte el flag para que no quede inconsistente y notifica.
     */
    public function failed(Throwable $e): void
    {
        Log::error('[NC-SIIGO] falló definitivo', [
            'dev' => $this->devolucionId,
            'error' => $e->getMessage(),
        ]);

        // withTrashed: si el registro se soft-deleteó entre dispatch y fallo definitivo,
        // igual debemos revertir el flag mentiroso.
        $dev = DropiDevolucion::withTrashed()->find($this->devolucionId);
        if ($dev) {
            $dev->update([
                'genero_nota_credito' => false,
                'notas' => trim(($dev->notas ?? '') . "\n[NC ERROR] {$e->getMessage()}"),
            ]);
        }
    }
}
