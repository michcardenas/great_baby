<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 4/6 · alertas_stock_config.
 * Sin CHECK constraint · invariante en Model + FK + domain layer.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('alertas_stock_config', 'producto_id')) {
            Schema::table('alertas_stock_config', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE alertas_stock_config a
              JOIN producto_variantes v ON a.variante_id = v.id
              SET a.producto_id = v.producto_id
              WHERE a.producto_id IS NULL AND a.variante_id IS NOT NULL
        ');

        $col = collect(DB::select("SHOW COLUMNS FROM alertas_stock_config LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE alertas_stock_config MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        try {
            DB::statement('ALTER TABLE alertas_stock_config DROP CONSTRAINT IF EXISTS chk_alerta_config_sujeto');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE alertas_stock_config MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('alertas_stock_config', 'producto_id')) {
            Schema::table('alertas_stock_config', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
