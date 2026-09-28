<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F11 · siigo_journal_id + siigo_sync_at en inventario_movimientos.
 * Cada movimiento kardex CONTABLE (traslados, mermas, sobrantes, ajustes
 * toma física) genera un asiento en SIIGO vía POST /v1/journals.
 *
 * NO todos los movimientos generan asiento: los tipos `entrada_compra` ya se
 * causan vía RecepcionCompra→PushRecepcionASiigo (F9). Los tipos
 * `carga_inicial_*` y `stock_inicial_*` tampoco (son datos históricos).
 * El Observer InventarioMovimientoObserver decide con una lista blanca.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventario_movimientos', function ($table) {
            if (! Schema::hasColumn('inventario_movimientos', 'siigo_journal_id')) {
                $table->string('siigo_journal_id', 60)->nullable();
            }
            if (! Schema::hasColumn('inventario_movimientos', 'siigo_sync_at')) {
                $table->timestamp('siigo_sync_at')->nullable();
            }
        });

        Schema::table('inventario_movimientos', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM inventario_movimientos WHERE Key_name = 'inv_mov_siigo_journal_id_uq'"
            ));
            if ($existente->isEmpty()) {
                $table->unique('siigo_journal_id', 'inv_mov_siigo_journal_id_uq');
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_movimientos', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM inventario_movimientos WHERE Key_name = 'inv_mov_siigo_journal_id_uq'"
            ));
            if ($existente->isNotEmpty()) {
                $table->dropUnique('inv_mov_siigo_journal_id_uq');
            }
            foreach (['siigo_sync_at', 'siigo_journal_id'] as $col) {
                if (Schema::hasColumn('inventario_movimientos', $col)) $table->dropColumn($col);
            }
        });
    }
};
