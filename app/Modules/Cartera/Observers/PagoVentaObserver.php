<?php

namespace App\Modules\Cartera\Observers;

use App\Modules\Cartera\Actions\RegistrarAsientoContable;
use App\Modules\Cartera\Models\PagoVenta;

/**
 * Observer explícito para PagoVenta.
 * Reemplaza los hooks de booted() que Auditable trait a veces intercepta.
 */
class PagoVentaObserver
{
    public function created(PagoVenta $pago): void
    {
        (new RegistrarAsientoContable())->pago($pago);
    }

    public function deleted(PagoVenta $pago): void
    {
        // 1. Reversar movimientos contables originados por este pago para no dejar arqueo huérfano
        \App\Modules\Cartera\Models\MovimientoContable::where('origen_type', PagoVenta::class)
            ->where('origen_id', $pago->id)
            ->delete();

        // 2. Recalcular saldo/estado de la factura
        $pago->factura?->recalcular();
    }

    public function restored(PagoVenta $pago): void
    {
        // Re-generar asiento contable (el delete lo borró)
        (new \App\Modules\Cartera\Actions\RegistrarAsientoContable())->pago($pago);
        $pago->factura?->recalcular();
    }
}
