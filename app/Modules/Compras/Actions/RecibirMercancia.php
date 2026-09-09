<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\OrdenCompraItem;
use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Dropi\Models\InventarioMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Confirma una recepción: mueve stock (+ movimiento inventario tipo 'entrada'),
 * actualiza cantidad_recibida en los items de OC, cambia estado OC a parcial/recibida
 * y genera asientos contables.
 *
 * Re-audit M2 PATRÓN C · TODO va dentro de una tx con `lockForUpdate` sobre la OC
 *   y sobre cada OrdenCompraItem afectado. Antes: dos POST concurrentes leían
 *   el mismo `cantidad_recibida`, sumaban su delta → stock inflado + asientos
 *   duplicados. Además la sobre-recepción NO se validaba (`Recepcion/Nueva.vue`
 *   solo tenía `:max` hint HTML).
 *
 * Re-audit M2 PATRÓN H · `InventarioMovimiento.cantidad` es INT; el trunc por
 *   `(int)round` perdía decimales cuando OC/Recepción manejan `decimal(12,3)`.
 *   Ahora abortamos si la cantidad recibida no es entera — hasta que exista
 *   migración `movimientos_inventario.cantidad → decimal`.
 */
class RecibirMercancia
{
    use AsAction;

    public function handle(RecepcionCompra $recepcion): RecepcionCompra
    {
        return DB::transaction(function () use ($recepcion) {
            // Lock la recepción para evitar doble-confirmación concurrente.
            $recepcion = RecepcionCompra::query()->whereKey($recepcion->id)->lockForUpdate()->first();
            if ($recepcion->estado === 'confirmada') {
                throw new InvalidArgumentException('La recepción ya fue confirmada.');
            }

            $recepcion->loadMissing('items.ordenItem', 'orden.items');

            if ($recepcion->items->isEmpty()) {
                throw new InvalidArgumentException('La recepción no tiene ítems.');
            }

            // Lock la OC completa antes de tocar sus items.
            OrdenCompra::query()->whereKey($recepcion->orden_id)->lockForUpdate()->first();

            foreach ($recepcion->items as $it) {
                $cantidadRecibida = (float) $it->cantidad_recibida;
                if ($cantidadRecibida <= 0) continue;

                // Lock el item para leer cantidad_recibida sin race y validar el tope.
                $ordenItem = OrdenCompraItem::query()->whereKey($it->orden_item_id)->lockForUpdate()->first();
                if (! $ordenItem) {
                    throw new InvalidArgumentException("Item OC {$it->orden_item_id} no existe.");
                }

                // Re-audit FUNC-C1 · guard sobre-recepción atómico.
                $pendiente = (float) $ordenItem->cantidad - (float) $ordenItem->cantidad_recibida;
                if ($cantidadRecibida > $pendiente + 0.0001) {
                    throw new InvalidArgumentException(sprintf(
                        'Sobre-recepción bloqueada · item "%s": pedido %.3f, ya recibido %.3f, esta recepción %.3f (pendiente %.3f).',
                        $ordenItem->descripcion ?? '?', $ordenItem->cantidad, $ordenItem->cantidad_recibida,
                        $cantidadRecibida, $pendiente,
                    ));
                }

                $ordenItem->cantidad_recibida = (float) $ordenItem->cantidad_recibida + $cantidadRecibida;
                $ordenItem->save();

                if ($it->variante_id && ! $recepcion->orden->esImportacion()) {
                    // Re-audit M3 ρ ι · kardex ya soporta decimal(14,4); no
                    //   abortamos por fraccionarios. Grabamos `costo_unit`
                    //   para que PMP real (patrón μ) pueda calcular sin
                    //   caer al precio_proveedor del maestro.
                    $costoUnit = (float) ($ordenItem->precio_unit ?? 0);
                    InventarioMovimiento::create([
                        'variante_id' => $it->variante_id,
                        'ubicacion_id' => $recepcion->bodega_id,
                        'tipo' => 'entrada_compra',
                        'cantidad' => round($cantidadRecibida, 4),
                        'costo_unit' => $costoUnit,
                        'referencia_tipo' => RecepcionCompra::class,
                        'referencia_id' => $recepcion->id,
                        'user_id' => auth()->id(),
                        'notas' => "Rec {$recepcion->numero} OC {$recepcion->orden->numero}",
                        'created_at' => now(),
                    ]);
                }
            }

            $totalRecibido = $recepcion->items->sum(fn ($i) => (float) $i->subtotal);
            $recepcion->total_recibido = $totalRecibido;
            $recepcion->estado = 'confirmada';
            $recepcion->confirmada_at = now();
            $recepcion->save();

            $this->actualizarEstadoOrden($recepcion->orden);

            RegistrarAsientoCompra::make()->recepcion($recepcion);

            return $recepcion->fresh(['items', 'orden']);
        });
    }

    protected function actualizarEstadoOrden(OrdenCompra $orden): void
    {
        $orden->load('items');
        $total = (float) $orden->items->sum(fn ($i) => (float) $i->cantidad);
        $recibido = (float) $orden->items->sum(fn ($i) => (float) $i->cantidad_recibida);

        if ($total <= 0) return;

        $nuevo = match (true) {
            $recibido <= 0 => EstadoOrdenCompra::Aprobada,
            $recibido >= $total - 0.0001 => EstadoOrdenCompra::Recibida,
            default => EstadoOrdenCompra::Parcial,
        };

        $orden->estado = $nuevo;
        $orden->save();
    }
}
