<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 3/6 · reservas_inventario.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('reservas_inventario', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE reservas_inventario r
              JOIN producto_variantes v ON r.variante_id = v.id
              SET r.producto_id = v.producto_id
              WHERE r.producto_id IS NULL
        ');

        DB::statement('ALTER TABLE reservas_inventario MODIFY variante_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE reservas_inventario
              ADD CONSTRAINT chk_reserva_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservas_inventario DROP CONSTRAINT IF EXISTS chk_reserva_sujeto');
        DB::statement('ALTER TABLE reservas_inventario MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        Schema::table('reservas_inventario', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
