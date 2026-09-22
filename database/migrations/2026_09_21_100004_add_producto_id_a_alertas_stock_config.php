<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 4/6 · alertas_stock_config.
 *
 *   Ahora una config de alerta puede ser por variante (granular) o por producto (agregado).
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('alertas_stock_config', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE alertas_stock_config a
              JOIN producto_variantes v ON a.variante_id = v.id
              SET a.producto_id = v.producto_id
              WHERE a.producto_id IS NULL
        ');

        DB::statement('ALTER TABLE alertas_stock_config MODIFY variante_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE alertas_stock_config
              ADD CONSTRAINT chk_alerta_config_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE alertas_stock_config DROP CONSTRAINT IF EXISTS chk_alerta_config_sujeto');
        DB::statement('ALTER TABLE alertas_stock_config MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        Schema::table('alertas_stock_config', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
