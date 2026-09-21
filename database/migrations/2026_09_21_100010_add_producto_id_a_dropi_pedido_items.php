<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F-QA1 · Fix CRÍTICO auditor QA E2E: dropi_pedido_items sin producto_id.
 *
 * Sin este campo, ProcesarEscaneoEmpaque nunca matchea productos agregados
 * ($item->producto_id devuelve null porque la columna no existe) y los listeners
 * DescontarInventarioAlEmpacar / RevertirEgresoAlDesempacar tampoco propagan.
 *
 * variante_id ya es nullable en el schema actual.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('variante_id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE dropi_pedido_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL AND i.variante_id IS NOT NULL
        ');

        DB::statement('
            ALTER TABLE dropi_pedido_items
              ADD CONSTRAINT chk_dropi_pedido_item_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL OR sku_dropi IS NOT NULL)
        ');
        // Nota: sku_dropi es fallback histórico para items importados sin match.
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE dropi_pedido_items DROP CONSTRAINT IF EXISTS chk_dropi_pedido_item_sujeto');
        Schema::table('dropi_pedido_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
