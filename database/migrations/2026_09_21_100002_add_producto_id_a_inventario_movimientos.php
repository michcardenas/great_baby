<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 2/6 · inventario_movimientos (el kardex).
 *
 *   Agrega `producto_id` NULL con backfill desde variante.producto_id.
 *   Hace `variante_id` NULLABLE (productos agregados no tienen variante).
 *
 * Fix Hostinger deploy · MariaDB 10.x del hosting rechaza CHECK constraint
 *   sobre una columna FK recién creada con error 1901. La invariante
 *   "al menos uno de {variante_id, producto_id}" queda enforced en:
 *     - Model hook InventarioMovimiento::creating (auto-populate producto_id
 *       desde variante_id)
 *     - FK constraint sobre producto_id
 *     - Domain layer (Actions polimórficas)
 *   Suficiente para producción; se puede añadir CHECK/trigger en migración
 *   futura cuando el hosting suba de versión.
 *
 * Idempotencia · Schema::hasColumn + verificación de nullability para poder
 *   re-correr después de un fallo parcial (ej. corrida previa que falló en
 *   el CHECK y dejó la columna creada).
 */
return new class extends Migration {

    public function up(): void
    {
        // 1) ADD COLUMN idempotente.
        if (! Schema::hasColumn('inventario_movimientos', 'producto_id')) {
            Schema::table('inventario_movimientos', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        // 2) Backfill idempotente (WHERE producto_id IS NULL).
        DB::statement('
            UPDATE inventario_movimientos m
              JOIN producto_variantes v ON m.variante_id = v.id
              SET m.producto_id = v.producto_id
              WHERE m.producto_id IS NULL AND m.variante_id IS NOT NULL
        ');

        // 3) MODIFY variante_id NULLABLE idempotente.
        $col = collect(DB::select("SHOW COLUMNS FROM inventario_movimientos LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE inventario_movimientos MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        // 4) Limpieza de CHECK antiguo si viene de una corrida previa fallida.
        try {
            DB::statement('ALTER TABLE inventario_movimientos DROP CONSTRAINT IF EXISTS chk_invmov_sujeto');
        } catch (\Throwable $e) { /* MySQL <8 no soporta IF EXISTS · ignorar */ }
    }

    public function down(): void
    {
        // Guard: no permitir rollback si hay movs agregados vivos (kardex corrupto).
        $huerfanos = (int) DB::table('inventario_movimientos')->whereNull('variante_id')->count();
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado · {$huerfanos} movimientos agregados (variante_id NULL) existen. "
                ."Migrar/limpiar antes de revertir, o el kardex quedará corrupto."
            );
        }
        DB::statement('ALTER TABLE inventario_movimientos MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('inventario_movimientos', 'producto_id')) {
            Schema::table('inventario_movimientos', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
