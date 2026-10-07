<?php

namespace App\Modules\Inventario\Actions;

// El modelo del kardex vive en el namespace Dropi por historia del proyecto:
// es el kardex de TODO el inventario, no sólo de ese canal.
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Support\Facades\Log;

/**
 * Saca del kardex la mercancía que se despacha en un pedido B2B.
 *
 * Hasta ahora una venta B2B NUNCA bajaba el stock: el único egreso de venta que
 * existía era el del canal Dropi (`DescontarInventarioAlEmpacar`). Se despachaban
 * 50 unidades, se facturaban, y el ERP seguía mostrándolas en bodega — el
 * inventario del sistema no volvía a coincidir con el estante nunca.
 *
 * Se descuenta al DESPACHAR, no al facturar, porque es el momento en que la
 * mercancía sale físicamente y es lo que el conteo del estante va a reflejar.
 */
class DescontarStockPorDespacho
{
    public const REFERENCIA_TIPO = 'pedido_b2b_despacho';

    /**
     * Debe llamarse DENTRO de la transacción del despacho.
     *
     * @return int cuántas líneas se descontaron
     */
    public function ejecutar(PedidoCliente $pedido, ?int $userId = null): int
    {
        // Idempotencia: si el pedido ya tiene su egreso, no duplicar. El despacho
        // ya se protege con `despachado_at`, pero esto cubre un reintento manual
        // o un despacho corregido a mano.
        $yaDescontado = InventarioMovimiento::query()
            ->where('referencia_tipo', self::REFERENCIA_TIPO)
            ->where('referencia_id', $pedido->id)
            ->exists();

        if ($yaDescontado) {
            return 0;
        }

        $ubicacionId = $pedido->ubicacion_origen_id;
        if (! $ubicacionId) {
            // Sin bodega de origen no sabemos de dónde sacarlo. No frenamos el
            // despacho —la mercancía ya está saliendo— pero queda el rastro para
            // que Jorge lo cuadre.
            Log::warning('[despacho] pedido sin ubicación de origen: no se descontó stock', [
                'pedido_id' => $pedido->id, 'numero' => $pedido->numero,
            ]);

            return 0;
        }

        $pedido->loadMissing('items');
        $lineas = 0;

        foreach ($pedido->items as $item) {
            // Ítem sin catálogo (texto libre): no hay nada que descontar.
            if (! $item->variante_id && ! $item->producto_id) {
                continue;
            }
            $cantidad = (int) ($item->cantidad ?? 0);
            if ($cantidad <= 0) {
                continue;
            }

            $esAgregado = $item->variante_id === null && $item->producto_id !== null;

            // Serializa contra un conteo o traslado simultáneo sobre el mismo
            // producto y bodega, igual que hace el egreso de Dropi.
            $lock = InventarioMovimiento::query()->where('ubicacion_id', $ubicacionId);
            $esAgregado
                ? $lock->where('producto_id', $item->producto_id)->whereNull('variante_id')
                : $lock->where('variante_id', $item->variante_id);
            $lock->lockForUpdate()->get();

            InventarioMovimiento::create([
                'variante_id' => $item->variante_id,
                'producto_id' => $item->producto_id,
                'ubicacion_id' => $ubicacionId,
                'tipo' => 'salida_venta',
                // Negativo: `SUM(cantidad)` es el saldo físico.
                'cantidad' => -$cantidad,
                'referencia_tipo' => self::REFERENCIA_TIPO,
                'referencia_id' => $pedido->id,
                'user_id' => $userId,
                'notas' => "Despacho pedido {$pedido->numero}"
                    . ($pedido->guia_transportadora ? " · guía {$pedido->guia_transportadora}" : '')
                    . ($esAgregado ? ' · AGREGADO' : ''),
            ]);
            $lineas++;
        }

        return $lineas;
    }
}
