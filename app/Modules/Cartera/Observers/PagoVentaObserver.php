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

    /**
     * Re-audit R3-C · si Aracely edita monto_aplicado / medio_pago / clasificación
     * post-creación (desde Filament), el asiento contable ANTES quedaba con el
     * valor viejo (cartera y contabilidad divergían). Ahora se re-genera.
     *
     * PagoVenta::updating bloquea cambios a `factura_id`; el resto de campos
     * SÍ es legítimo editar (corrección de captura). Este handler mantiene
     * consistencia contable con la edición.
     */
    public function updated(PagoVenta $pago): void
    {
        $camposContables = ['monto_recibido', 'monto_aplicado', 'diferencia',
            'clasificacion_diferencia', 'medio_pago', 'fecha'];
        if (! $pago->wasChanged($camposContables)) return;

        // Re-generar asiento (limpiarPrevios → forceDelete → nuevo).
        (new RegistrarAsientoContable())->pago($pago);
        $pago->factura?->recalcular();
    }

    public function deleted(PagoVenta $pago): void
    {
        // 1. Reversar movimientos contables originados por este pago.
        //    forceDelete físico — no deja shadow rows en `movimientos_contables`.
        \App\Modules\Cartera\Models\MovimientoContable::where('origen_type', PagoVenta::class)
            ->where('origen_id', $pago->id)
            ->forceDelete();

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
