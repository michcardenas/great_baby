<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Models\Traslado;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ejecuta un traslado en 2 patas idempotentes:
 *   1. Salida de origen (kardex -cantidad)
 *   2. Entrada en destino (kardex +cantidad)
 * Ambas escrituras comparten referencia_tipo=Traslado, referencia_id, y user_id.
 *
 * Valida stock disponible en origen ANTES de mover.
 */
class EjecutarTraslado
{
    use AsAction;

    public function __construct(protected StockService $stock)
    {
    }

    public function handle(Traslado $traslado): Traslado
    {
        return DB::transaction(function () use ($traslado) {
            if ($traslado->estado === EstadoTraslado::Recibido) {
                throw new InvalidArgumentException('El traslado ya fue ejecutado.');
            }
            if ($traslado->origen_id === $traslado->destino_id) {
                throw new InvalidArgumentException('Origen y destino no pueden ser la misma bodega.');
            }

            $traslado->load('items');
            if ($traslado->items->isEmpty()) {
                throw new InvalidArgumentException('El traslado no tiene ítems.');
            }

            foreach ($traslado->items as $it) {
                $disp = $this->stock->saldoDisponible($it->variante_id, $traslado->origen_id);
                $req = (int) $it->cantidad_solicitada;
                if ($disp < $req) {
                    throw new InvalidArgumentException(
                        "Stock insuficiente en origen para variante {$it->variante_id}: disponible {$disp}, requerido {$req}."
                    );
                }
            }

            foreach ($traslado->items as $it) {
                $cant = (int) $it->cantidad_solicitada;

                InventarioMovimiento::create([
                    'variante_id' => $it->variante_id,
                    'ubicacion_id' => $traslado->origen_id,
                    'tipo' => 'traslado_salida',
                    'cantidad' => -$cant,
                    'referencia_tipo' => Traslado::class,
                    'referencia_id' => $traslado->id,
                    'user_id' => auth()->id(),
                    'notas' => "Traslado {$traslado->numero} → {$traslado->destino?->nombre}",
                    'created_at' => now(),
                ]);

                InventarioMovimiento::create([
                    'variante_id' => $it->variante_id,
                    'ubicacion_id' => $traslado->destino_id,
                    'tipo' => 'traslado_entrada',
                    'cantidad' => $cant,
                    'referencia_tipo' => Traslado::class,
                    'referencia_id' => $traslado->id,
                    'user_id' => auth()->id(),
                    'notas' => "Traslado {$traslado->numero} ← {$traslado->origen?->nombre}",
                    'created_at' => now(),
                ]);

                $it->cantidad_ejecutada = $cant;
                $it->save();
            }

            $traslado->estado = EstadoTraslado::Recibido;
            $traslado->fecha_ejecucion = now();
            $traslado->ejecutado_por = auth()->id();
            $traslado->save();

            return $traslado->fresh(['items', 'origen', 'destino']);
        });
    }
}
