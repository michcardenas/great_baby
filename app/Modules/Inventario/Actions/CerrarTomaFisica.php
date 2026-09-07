<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Models\TomaFisica;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Cierra una toma física:
 *   1. Genera movimiento de inventario por cada diferencia (+ o -)
 *   2. Genera asiento contable: sobrante 1435 debe · 6135 haber (costo)
 *                               faltante 5195 debe · 1435 haber
 *   3. Actualiza items_diferentes y valor_ajuste
 */
class CerrarTomaFisica
{
    use AsAction;

    public function handle(TomaFisica $toma): TomaFisica
    {
        return DB::transaction(function () use ($toma) {
            if ($toma->estado !== EstadoTomaFisica::EnConteo) {
                throw new InvalidArgumentException('Sólo se cierran tomas en conteo.');
            }

            $toma->load('items');

            $itemsConDiferencia = 0;
            $valorAjuste = 0.0;

            foreach ($toma->items as $it) {
                if ($it->cantidad_contada === null) {
                    continue;
                }
                $diff = (int) $it->diferencia;
                if ($diff === 0) {
                    continue;
                }
                $itemsConDiferencia++;
                $valorAjuste += $diff * (float) ($it->costo_unit ?? 0);

                InventarioMovimiento::create([
                    'variante_id' => $it->variante_id,
                    'ubicacion_id' => $toma->ubicacion_id,
                    'tipo' => 'ajuste_toma',
                    'cantidad' => $diff,
                    'referencia_tipo' => TomaFisica::class,
                    'referencia_id' => $toma->id,
                    'user_id' => auth()->id(),
                    'notas' => "Toma física {$toma->numero} · sistema={$it->saldo_sistema} · contado={$it->cantidad_contada}",
                    'created_at' => now(),
                ]);
            }

            if (abs($valorAjuste) > 0.01) {
                $abs = abs($valorAjuste);
                if ($valorAjuste > 0) {
                    // Sobrante: 1435 debe (aumenta inventario), 4295 haber (ingreso extraordinario)
                    $cuentaDebito = '1435';
                    $cuentaCredito = '4295';
                } else {
                    // Faltante: 5195 debe (gasto), 1435 haber (baja inventario)
                    $cuentaDebito = '5195';
                    $cuentaCredito = '1435';
                }

                MovimientoContable::create([
                    'fecha' => now(),
                    'cuenta_puc' => $cuentaDebito,
                    'debe' => $abs, 'haber' => 0,
                    'origen_type' => TomaFisica::class, 'origen_id' => $toma->id,
                    'descripcion' => "Ajuste toma física {$toma->numero}",
                    'user_id' => auth()->id(),
                ]);
                MovimientoContable::create([
                    'fecha' => now(),
                    'cuenta_puc' => $cuentaCredito,
                    'debe' => 0, 'haber' => $abs,
                    'origen_type' => TomaFisica::class, 'origen_id' => $toma->id,
                    'descripcion' => "Contra-ajuste toma {$toma->numero}",
                    'user_id' => auth()->id(),
                ]);
            }

            $toma->items_diferentes = $itemsConDiferencia;
            $toma->valor_ajuste = $valorAjuste;
            $toma->estado = EstadoTomaFisica::Ajustada;
            $toma->cerrada_at = now();
            $toma->cerrada_por = auth()->id();
            $toma->save();

            return $toma->fresh(['items']);
        });
    }
}
