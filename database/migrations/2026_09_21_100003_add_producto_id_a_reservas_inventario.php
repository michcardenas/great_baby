<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 3/6 · reservas_inventario.
 * Sin CHECK constraint (Hostinger MariaDB no lo acepta sobre FK recién creada).
 * Invariante enforced en Model hooks + FK + domain layer.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('reservas_inventario', 'producto_id')) {
            Schema::table('reservas_inventario', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE reservas_inventario r
              JOIN producto_variantes v ON r.variante_id = v.id
              SET r.producto_id = v.producto_id
              WHERE r.producto_id IS NULL AND r.variante_id IS NOT NULL
        ');

        $col = collect(DB::select("SHOW COLUMNS FROM reservas_inventario LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE reservas_inventario MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        try {
            DB::statement('ALTER TABLE reservas_inventario DROP CONSTRAINT IF EXISTS chk_reserva_sujeto');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE reservas_inventario MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('reservas_inventario', 'producto_id')) {
            Schema::table('reservas_inventario', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
