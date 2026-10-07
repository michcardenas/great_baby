<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Cartera\Models\NotaCredito;
use App\Modules\Siigo\Jobs\PushNotaCreditoASiigo;
use Illuminate\Support\Facades\DB;

/**
 * Sprint 4 · B.1 · Observer NotaCredito.
 * Dispara push SIIGO SOLO para NC MANUALES (no las de Dropi).
 * Las NC de Dropi ya tienen su propio flujo (EmitirNotaCreditoDropi) que
 * llama al SiigoEmisionService por otro canal.
 */
class NotaCreditoObserver
{
    public function created(NotaCredito $nc): void
    {
        // Una NC reduce lo que el cliente debe. `FacturaVenta::recalcular()` ya
        // sabe restarlas, pero nadie lo llamaba al emitirlas: la factura quedaba
        // con el saldo completo y el barrido `cartera:cobrar` de las 9am seguía
        // mandando WhatsApp cobrando una deuda ya devuelta.
        $nc->factura?->recalcular();

        // Solo NC manuales · las de Dropi tienen su propio flujo.
        if ($nc->devolucion_dropi_id) return;
        if ($nc->siigo_id) return;

        $id = $nc->id;
        DB::afterCommit(function () use ($id) {
            PushNotaCreditoASiigo::dispatch($id);
            // A.6 · WhatsApp cadena valor · via cola async (QA-FIX #5).
            \App\Modules\Notificaciones\Jobs\NotificarCadenaValorJob::dispatch('nc-emitida', $id);
        });
    }

    /**
     * Anular o rechazar una NC devuelve la deuda a la factura, y cambiar su
     * valor la ajusta. `recalcular()` excluye las rechazadas/anuladas, así que
     * basta con volver a pedírselo.
     */
    public function updated(NotaCredito $nc): void
    {
        if ($nc->wasChanged(['valor', 'estado', 'factura_id'])) {
            $nc->factura?->recalcular();
        }
    }

    public function deleted(NotaCredito $nc): void
    {
        $nc->factura?->recalcular();
    }

    public function restored(NotaCredito $nc): void
    {
        $nc->factura?->recalcular();
    }
}
