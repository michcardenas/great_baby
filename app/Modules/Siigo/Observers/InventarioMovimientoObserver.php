<?php

namespace App\Modules\Siigo\Observers;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Siigo\Jobs\PushAsientoASiigo;
use Illuminate\Support\Facades\DB;

/**
 * F11 · Observer que dispara asiento SIIGO por movimientos de kardex CONTABLES.
 *
 * LISTA BLANCA · solo estos tipos generan asiento en SIIGO:
 *   - traslado_salida / traslado_entrada
 *   - merma / faltante
 *   - sobrante
 *   - ajuste_toma_fisica
 *
 * NO generan asiento (excluidos explícitamente):
 *   - entrada_compra          → ya se causa vía F9 (RecepcionCompra → factura compra)
 *   - carga_inicial_cliente   → datos históricos, no van a SIIGO
 *   - stock_inicial_form      → idem
 *   - ingreso                 → depende del origen (revisar caso por caso; por ahora skip)
 *
 * Envuelto en DB::afterCommit para no encolar jobs huérfanos por rollback.
 */
class InventarioMovimientoObserver
{
    /**
     * Tipos de movimiento kardex que DISPARAN asiento SIIGO.
     */
    private const TIPOS_ASIENTO = [
        'traslado_salida',
        'traslado_entrada',
        // BUG-TRASL · reversas de traslado (anulación) también deben llegar a
        // SIIGO · antes el asiento original quedaba sin contrapartida al anular.
        'traslado_reversa_salida',
        'traslado_reversa_entrada',
        'merma',
        'faltante',
        'sobrante',
        'ajuste_toma_fisica',
    ];

    public function created(InventarioMovimiento $m): void
    {
        if (! in_array($m->tipo, self::TIPOS_ASIENTO, true)) return;
        if ($m->siigo_journal_id) return;

        // Sin costo_unit no hay asiento posible (valor = 0).
        if ((float) $m->costo_unit <= 0) return;

        $id = $m->id;
        DB::afterCommit(function () use ($id) {
            PushAsientoASiigo::dispatch($id);
        });
    }
}
