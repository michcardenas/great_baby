<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F-QA1 · factura_venta_items sin producto_id.
 * Sin CHECK · invariante en Model booted::creating hook + FK + domain layer.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('factura_venta_items', 'producto_id')) {
            Schema::table('factura_venta_items', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('variante_id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE factura_venta_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL AND i.variante_id IS NOT NULL
        ');

        try {
            DB::statement('ALTER TABLE factura_venta_items DROP CONSTRAINT IF EXISTS chk_factura_venta_item_sujeto');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        if (Schema::hasColumn('factura_venta_items', 'producto_id')) {
            Schema::table('factura_venta_items', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
