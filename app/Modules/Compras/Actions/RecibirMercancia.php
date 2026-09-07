<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\RecepcionCompra;
use App\Modules\Dropi\Models\InventarioMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Confirma una recepción: mueve stock (+ movimiento inventario tipo 'entrada'),
 * actualiza cantidad_recibida en los items de OC, cambia estado OC a parcial/recibida
 * y genera asientos contables.
 */
class RecibirMercancia
{
    use AsAction;

    public function handle(RecepcionCompra $recepcion): RecepcionCompra
    {
        return DB::transaction(function () use ($recepcion) {
            if ($recepcion->estado === 'confirmada') {
                throw new InvalidArgumentException('La recepción ya fue confirmada.');
            }

            $recepcion->loadMissing('items.ordenItem', 'orden.items');

            if ($recepcion->items->isEmpty()) {
                throw new InvalidArgumentException('La recepción no tiene ítems.');
            }

            foreach ($recepcion->items as $it) {
                if ((float) $it->cantidad_recibida <= 0) {
                    continue;
                }

                $ordenItem = $it->ordenItem;
                $ordenItem->cantidad_recibida = (float) $ordenItem->cantidad_recibida + (float) $it->cantidad_recibida;
                $ordenItem->save();

                if ($it->variante_id && ! $recepcion->orden->esImportacion()) {
                    InventarioMovimiento::create([
                        'variante_id' => $it->variante_id,
                        'ubicacion_id' => $recepcion->bodega_id,
                        'tipo' => 'entrada_compra',
                        'cantidad' => (int) round((float) $it->cantidad_recibida),
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

        if ($total <= 0) {
            return;
        }

        $nuevo = match (true) {
            $recibido <= 0 => EstadoOrdenCompra::Aprobada,
            $recibido >= $total => EstadoOrdenCompra::Recibida,
            default => EstadoOrdenCompra::Parcial,
        };

        $orden->estado = $nuevo;
        $orden->save();
    }
}
