<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 6/6 · tomas_fisicas_items.
 * Sin CHECK · invariante en Model + FK + domain layer.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('tomas_fisicas_items', 'producto_id')) {
            Schema::table('tomas_fisicas_items', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('toma_id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE tomas_fisicas_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL AND i.variante_id IS NOT NULL
        ');

        $col = collect(DB::select("SHOW COLUMNS FROM tomas_fisicas_items LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE tomas_fisicas_items MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        try {
            DB::statement('ALTER TABLE tomas_fisicas_items DROP CONSTRAINT IF EXISTS chk_toma_item_sujeto');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tomas_fisicas_items MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('tomas_fisicas_items', 'producto_id')) {
            Schema::table('tomas_fisicas_items', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
