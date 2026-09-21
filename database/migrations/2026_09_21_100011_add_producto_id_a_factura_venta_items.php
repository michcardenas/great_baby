<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F-QA1 · Fix CRÍTICO auditor QA E2E: factura_venta_items sin producto_id.
 *
 * Al facturar un pedido con item agregado, se pierde toda referencia al catálogo
 * (variante_id = NULL). Reportes de ventas por producto, SIIGO export y NC no
 * pueden rastrearlo. Backfill desde variante donde aplique.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('factura_venta_items', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('variante_id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE factura_venta_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL AND i.variante_id IS NOT NULL
        ');

        // Fix H-1 re-audit · CHECK trazabilidad al catálogo.
        //   Antes: cláusula `descripcion IS NOT NULL` era TAUTOLÓGICA porque
        //   la columna `descripcion` es NOT NULL en el schema original
        //   (create_factura_venta_items_table.php). El CHECK no enforceaba
        //   nada. Ahora: exigimos variante O producto para forzar
        //   trazabilidad DIAN/SIIGO/notas crédito. Líneas manuales usan
        //   producto_id = producto especial "SERVICIO" configurable.
        DB::statement('
            ALTER TABLE factura_venta_items
              ADD CONSTRAINT chk_factura_venta_item_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE factura_venta_items DROP CONSTRAINT IF EXISTS chk_factura_venta_item_sujeto');
        Schema::table('factura_venta_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
