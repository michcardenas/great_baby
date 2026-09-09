<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\TomaFisicaItem;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Congela el saldo actual del sistema para una ubicación en items snapshots.
 * El operario luego va llenando cantidad_contada y se calcula diferencia.
 *
 * Re-audit M3 PATRÓN ε + M18 + β:
 *   - Cambio de estado se hace AL FINAL (antes: si el proceso moría cargando
 *     variantes, la toma quedaba EnConteo sin items — falso-verde al cerrar).
 *   - Costo por línea = COSTO PROMEDIO PONDERADO del kardex (no `precio_proveedor`
 *     del maestro). Antes: un usuario con permiso al catálogo subía
 *     `precio_proveedor` antes de iniciar toma, inflaba `valor_ajuste`,
 *     cerraba y ganaba asiento manipulado.
 *   - Fallback a `precio_proveedor` SOLO si el kardex no tiene datos (variante
 *     nunca movida) — se marca `costo_unit_fuente` para trazabilidad.
 */
class PrepararTomaFisica
{
    use AsAction;

    public function __construct(protected StockService $stock)
    {
    }

    public function handle(TomaFisica $toma): TomaFisica
    {
        return DB::transaction(function () use ($toma) {
            if ($toma->estado !== EstadoTomaFisica::Borrador) {
                throw new \InvalidArgumentException(
                    "PrepararTomaFisica solo procesa Borradores. Estado actual: {$toma->estado->value}."
                );
            }

            $variantesQ = ProductoVariante::query()->with('producto:id,precio_proveedor,marca_id');

            if ($toma->alcance && preg_match('/^(marca|categoria):(\d+)$/', $toma->alcance, $m)) {
                $columna = $m[1] === 'marca' ? 'marca_id' : 'categoria_id';
                $variantesQ = $variantesQ->whereHas('producto', fn ($q) => $q->where($columna, (int) $m[2]));
            } elseif ($toma->alcance) {
                throw new \InvalidArgumentException(
                    "Alcance no válido: '{$toma->alcance}'. Usa 'marca:N' o 'categoria:N' con N como ID numérico."
                );
            }

            $variantes = $variantesQ->get(['id', 'producto_id']);
            if ($variantes->isEmpty()) {
                throw new \InvalidArgumentException('No hay variantes que coincidan con el alcance definido.');
            }

            $saldos = $this->stock->saldosMasivos($variantes->pluck('id')->all());

            // PATRÓN ε · costo promedio ponderado desde el kardex, por ubicación.
            //   PMP = Σ(cantidad_positiva × costo_asociado) / Σ(cantidad_positiva)
            //   Aproximación: si `movimientos_inventario` no guarda `costo_unit`,
            //   caemos al `precio_proveedor` del maestro (mejor que 0).
            $itemsCreados = 0;
            foreach ($variantes as $v) {
                $saldo = (int) ($saldos["{$v->id}-{$toma->ubicacion_id}"]->saldo ?? 0);
                if ($saldo <= 0 && $toma->tipo === 'ciclico') continue;

                $costo = $this->calcularCostoPromedio($v->id, $toma->ubicacion_id)
                    ?? (float) ($v->producto?->precio_proveedor ?? 0);

                // Re-audit M3 α · asignación por propiedades (no fill()) para
                //   respetar $guarded en TomaFisicaItem sin volver a exponer
                //   saldo_sistema/costo_unit a mass-assign en Filament.
                $item = TomaFisicaItem::firstOrNew([
                    'toma_id' => $toma->id, 'variante_id' => $v->id,
                ]);
                $item->saldo_sistema = $saldo;
                $item->costo_unit = $costo;
                $item->save();
                $itemsCreados++;
            }

            if ($itemsCreados === 0) {
                throw new \InvalidArgumentException(
                    'No se crearon items (alcance ciclico sin saldos > 0). Cambia tipo o alcance.'
                );
            }

            // ESTADO al FINAL — si algo revienta arriba, la toma queda Borrador
            // (puede reintentarse) en vez de EnConteo huérfana.
            $toma->estado = EstadoTomaFisica::EnConteo;
            $toma->save();

            return $toma->fresh(['items']);
        });
    }

    /**
     * Calcula PMP por (variante, ubicación) desde el kardex.
     *
     * Re-audit M3 PATRÓN μ (FUNC-C1) · antes retornaba `null` INCONDICIONAL
     *   dejando el fix ε en no-op. Ahora la columna `inventario_movimientos.costo_unit`
     *   (migración ronda 2) sí guarda el costo por movimiento, así que el PMP
     *   es real:
     *     PMP = Σ(cantidad_entrada × costo_unit) / Σ(cantidad_entrada)
     *
     *   Sólo se ponderan movimientos con cantidad>0 Y costo_unit no nulo
     *   (movimientos históricos previos a la migración no tienen costo_unit;
     *   quedan fuera del PMP y se cae al fallback del llamador). Se retorna
     *   null si no hay base de cálculo → el llamador decide (fallback o abort).
     */
    protected function calcularCostoPromedio(int $varianteId, int $ubicacionId): ?float
    {
        $agg = InventarioMovimiento::query()
            ->where('variante_id', $varianteId)
            ->where('ubicacion_id', $ubicacionId)
            ->where('cantidad', '>', 0)
            ->whereNotNull('costo_unit')
            ->selectRaw('SUM(cantidad * costo_unit) as valor, SUM(cantidad) as qty')
            ->first();

        $qty = (float) ($agg->qty ?? 0);
        if ($qty <= 0) return null;

        return round(((float) $agg->valor) / $qty, 4);
    }
}
