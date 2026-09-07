<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Compras\Models\RecepcionCompraItem;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Crea una RecepcionCompra en borrador con todos los items pendientes
 * de la OC (cantidad = pendiente por defecto). El usuario luego edita antes de confirmar.
 */
class PrepararRecepcionDesdeOC
{
    use AsAction;

    public function handle(OrdenCompra $orden, ?int $bodegaId = null): RecepcionCompra
    {
        return DB::transaction(function () use ($orden, $bodegaId) {
            // Fix auditor #15
            $estadoValor = $orden->estado?->value ?? $orden->estado;
            if (! in_array($estadoValor, ['aprobada', 'parcial'], true)) {
                throw new \InvalidArgumentException(
                    'Solo se pueden recibir órdenes aprobadas o parcialmente recibidas. Estado actual: ' . $estadoValor
                );
            }
            $bodegaFinal = $bodegaId ?? $orden->bodega_id;
            if (! $bodegaFinal) {
                throw new \InvalidArgumentException(
                    'La OC no tiene bodega asignada. Edita la OC y asigna una bodega destino antes de recibir mercancía.'
                );
            }

            $orden->loadMissing('items');

            $rec = RecepcionCompra::create([
                'numero' => RecepcionCompra::siguienteNumero(),
                'orden_id' => $orden->id,
                'bodega_id' => $bodegaFinal,
                'recibido_por' => auth()->id(),
                'fecha_recepcion' => now(),
            ]);

            foreach ($orden->items as $item) {
                $pendiente = $item->pendiente();
                if ($pendiente <= 0) {
                    continue;
                }
                RecepcionCompraItem::create([
                    'recepcion_id' => $rec->id,
                    'orden_item_id' => $item->id,
                    'producto_id' => $item->producto_id,
                    'variante_id' => $item->variante_id,
                    'cantidad_recibida' => $pendiente,
                    'costo_unit' => $item->precio_unit,
                ]);
            }

            return $rec->fresh(['items']);
        });
    }
}
