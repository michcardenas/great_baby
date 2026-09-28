<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Siigo\Jobs\PushRecepcionASiigo;
use Illuminate\Support\Facades\DB;

/**
 * F9 · Observer que dispara el push de la factura de compra a SIIGO cuando
 * una recepción pasa a estado 'confirmada' (mercancía recibida = compra causada).
 *
 * Envuelto en `DB::afterCommit` para que un rollback NO deje jobs huérfanos.
 * El Job maneja idempotencia (skip si ya tiene siigo_id) y kill-switch.
 *
 * IMPORTANTE · Este observer se REGISTRA en `RecepcionCompra::booted()` o
 * en `AppServiceProvider::boot()`. Mientras no se registre, no dispara nada
 * (kill-switch efectivo mientras se hacen pruebas).
 */
class RecepcionCompraObserver
{
    public function updated(RecepcionCompra $r): void
    {
        // Solo cuando pasa a 'confirmada' desde otro estado y aún no tiene siigo_id.
        if (! $r->wasChanged('estado')) return;
        if ($r->estado !== 'confirmada') return;
        if ($r->siigo_id) return;

        $id = $r->id;
        DB::afterCommit(function () use ($id, $r) {
            PushRecepcionASiigo::dispatch($id);
            $this->notificarWhatsApp($r);
        });
    }

    public function created(RecepcionCompra $r): void
    {
        // Si se crea ya confirmada (import masivo, seeder), también dispatchea.
        if ($r->estado !== 'confirmada' || $r->siigo_id) return;

        $id = $r->id;
        DB::afterCommit(function () use ($id, $r) {
            PushRecepcionASiigo::dispatch($id);
            $this->notificarWhatsApp($r);
        });
    }

    // A.6 · WhatsApp cadena valor · notifica al proveedor via cola async.
    protected function notificarWhatsApp(RecepcionCompra $r): void
    {
        \App\Modules\Notificaciones\Jobs\NotificarCadenaValorJob::dispatch('recepcion-confirmada', $r->id);
    }
}
