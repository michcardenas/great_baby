<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Models\DropiDevolucion;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Al registrar una devolución Dropi, escribe el asiento inverso:
 *   4175 (Devoluciones en ventas) DÉBITO por valor pedido
 *   1305 (CxC) CRÉDITO — reduce el saldo del vendedor B2B (si aplica)
 *   1435 (Inventario) DÉBITO si vuelve a stock
 *   6135 (Costo de mercancía vendida) CRÉDITO si vuelve a stock
 */
class ContabilizarDevolucionDropi
{
    use AsAction;

    public function handle(DropiDevolucion $devolucion): int
    {
        $pedido = $devolucion->pedido;
        if (! $pedido) return 0;

        $valor = (float) $pedido->monto_esperado_proveedor;
        if ($valor <= 0) return 0;

        // Ya contabilizada
        if (MovimientoContable::where('origen_type', DropiDevolucion::class)
            ->where('origen_id', $devolucion->id)->exists()) {
            return 0;
        }

        $movs = 0;

        // Reverso del ingreso por venta
        MovimientoContable::create([
            'fecha' => $devolucion->recibido_at?->toDateString() ?? now()->toDateString(),
            'cuenta_puc' => '4175',
            'debe' => $valor, 'haber' => 0,
            'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
            'descripcion' => "Devolución guía {$pedido->guia}",
            'user_id' => auth()->id(),
        ]);
        $movs++;

        // Si reingresa a stock, ajuste de inventario y costo
        if ($devolucion->destino_inventario === 'reingreso') {
            MovimientoContable::create([
                'fecha' => $devolucion->recibido_at?->toDateString() ?? now()->toDateString(),
                'cuenta_puc' => '1435',
                'debe' => $valor, 'haber' => 0,
                'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
                'descripcion' => "Reingreso inventario devolución {$pedido->guia}",
                'user_id' => auth()->id(),
            ]);
            MovimientoContable::create([
                'fecha' => $devolucion->recibido_at?->toDateString() ?? now()->toDateString(),
                'cuenta_puc' => '6135',
                'debe' => 0, 'haber' => $valor,
                'origen_type' => DropiDevolucion::class, 'origen_id' => $devolucion->id,
                'descripcion' => "Reverso costo devolución {$pedido->guia}",
                'user_id' => auth()->id(),
            ]);
            $movs += 2;
        }

        return $movs;
    }
}
