<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Enums\EstadoImportacion;
use App\Modules\Compras\Models\Importacion;
use App\Modules\Compras\Models\ImportacionLinea;
use App\Modules\Compras\Models\OrdenCompraItem;
use App\Modules\Dropi\Models\InventarioMovimiento;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Liquida importación:
 *   1. Recolecta líneas (OC + items) asociadas a la importación.
 *   2. Prorratea gastos capitalizables por método (valor|cantidad|peso|volumen).
 *   3. Escribe compras_importacion_lineas con costo_final_unit.
 *   4. Actualiza costo_prorrateado_unit en compra_orden_items.
 *   5. Actualiza estado y totales de la importación.
 *   6. Dispara asiento contable (1465 → 1435 + gastos).
 */
class LiquidarImportacion
{
    use AsAction;

    public function handle(Importacion $importacion): Importacion
    {
        return DB::transaction(function () use ($importacion) {
            $importacion->loadMissing('ordenes.items', 'gastos');

            $items = collect();
            $itemsBodega = [];
            foreach ($importacion->ordenes as $orden) {
                foreach ($orden->items as $item) {
                    $items->push([
                        'orden_item' => $item,
                        'peso_pivot' => (float) ($orden->pivot->peso_kg ?? 0),
                        'volumen_pivot' => (float) ($orden->pivot->volumen_m3 ?? 0),
                    ]);
                    $itemsBodega[$item->id] = $orden->bodega_id;
                }
            }

            if ($items->isEmpty()) {
                throw new InvalidArgumentException('No hay ítems en las órdenes ligadas.');
            }

            $totalValor = $items->sum(fn ($x) => (float) $x['orden_item']->subtotal);
            $totalCantidad = $items->sum(fn ($x) => (float) $x['orden_item']->cantidad);
            $totalPeso = $items->sum(fn ($x) => $x['peso_pivot']);
            $totalVolumen = $items->sum(fn ($x) => $x['volumen_pivot']);

            $gastosCap = $importacion->gastos->where('capitalizable', true);

            ImportacionLinea::where('importacion_id', $importacion->id)->delete();

            $fobTotal = 0;
            $gastoTotal = 0;

            foreach ($items as $x) {
                /** @var OrdenCompraItem $item */
                $item = $x['orden_item'];

                $costoFobTotal = (float) $item->subtotal;
                $prorrateo = 0.0;

                foreach ($gastosCap as $gasto) {
                    $base = match ($gasto->metodo_prorrateo) {
                        'cantidad' => $totalCantidad > 0 ? ((float) $item->cantidad / $totalCantidad) : 0,
                        'peso' => $totalPeso > 0 ? ($x['peso_pivot'] / $totalPeso) : 0,
                        'volumen' => $totalVolumen > 0 ? ($x['volumen_pivot'] / $totalVolumen) : 0,
                        default => $totalValor > 0 ? ($costoFobTotal / $totalValor) : 0,
                    };
                    $prorrateo += (float) $gasto->monto_base * $base;
                }

                $prorrateo = round($prorrateo, 2);
                $costoFinalTotal = round($costoFobTotal + $prorrateo, 2);
                $costoFinalUnit = (float) $item->cantidad > 0
                    ? round($costoFinalTotal / (float) $item->cantidad, 4)
                    : 0;

                ImportacionLinea::create([
                    'importacion_id' => $importacion->id,
                    'orden_item_id' => $item->id,
                    'producto_id' => $item->producto_id,
                    'variante_id' => $item->variante_id,
                    'cantidad' => $item->cantidad,
                    'costo_fob_unit' => $item->precio_unit,
                    'costo_fob_total' => $costoFobTotal,
                    'gasto_prorrateado' => $prorrateo,
                    'costo_final_unit' => $costoFinalUnit,
                    'costo_final_total' => $costoFinalTotal,
                ]);

                $item->costo_prorrateado_unit = $costoFinalUnit;
                $item->save();

                // BUG AUDITOR #7: importación no ingresaba al kardex.
                // Al liquidar, mover stock a la bodega asignada en la OC de origen.
                $bodegaId = $itemsBodega[$item->id] ?? null;
                if ($bodegaId && $item->variante_id && (int) $item->cantidad > 0) {
                    InventarioMovimiento::create([
                        'variante_id' => $item->variante_id,
                        'ubicacion_id' => $bodegaId,
                        'tipo' => 'entrada_importacion',
                        'cantidad' => (int) round((float) $item->cantidad),
                        'referencia_tipo' => Importacion::class,
                        'referencia_id' => $importacion->id,
                        'user_id' => auth()->id(),
                        'notas' => "Liquidación IMP {$importacion->numero} · costo unit \${$costoFinalUnit}",
                        'created_at' => now(),
                    ]);
                }

                $fobTotal += $costoFobTotal;
                $gastoTotal += $prorrateo;
            }

            $importacion->valor_fob = round($fobTotal, 2);
            $importacion->valor_gastos = round($gastoTotal, 2);
            $importacion->valor_total_costo = round($fobTotal + $gastoTotal, 2);
            $importacion->fecha_liquidacion = $importacion->fecha_liquidacion ?? now();
            $importacion->estado = EstadoImportacion::Liquidada;
            $importacion->liquidada_por = auth()->id();
            $importacion->save();

            RegistrarAsientoCompra::make()->liquidacion($importacion);

            return $importacion->fresh(['ordenes', 'gastos', 'lineas']);
        });
    }
}
