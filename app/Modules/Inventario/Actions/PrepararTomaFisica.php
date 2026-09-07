<?php

namespace App\Modules\Inventario\Actions;

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
            $toma->estado = EstadoTomaFisica::EnConteo;
            $toma->save();

            $variantesQ = ProductoVariante::query()->with('producto:id,precio_proveedor,marca_id');

            // Alcance con whitelist: marca:N o categoria:N (bug auditor #11 + #21)
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
                return $toma;
            }

            $saldos = $this->stock->saldosMasivos($variantes->pluck('id')->all());

            foreach ($variantes as $v) {
                $saldo = (int) ($saldos["{$v->id}-{$toma->ubicacion_id}"]->saldo ?? 0);
                if ($saldo <= 0 && $toma->tipo === 'ciclico') {
                    continue;
                }

                // BUG AUDITOR #6: costo_unit siempre null → asiento contable en $0.
                // Fallback: precio_proveedor del producto (aproximación mientras no exista costo_promedio).
                $costo = (float) ($v->producto?->precio_proveedor ?? 0);

                TomaFisicaItem::updateOrCreate(
                    ['toma_id' => $toma->id, 'variante_id' => $v->id],
                    ['saldo_sistema' => $saldo, 'costo_unit' => $costo]
                );
            }

            return $toma->fresh(['items']);
        });
    }
}
