<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual — Migración 2/6 · inventario_movimientos (el kardex).
 *
 *   Agrega `producto_id` NOT NULL con backfill desde variante.producto_id.
 *   Hace `variante_id` NULLABLE (productos agregados no tienen variante).
 *   CHECK: al menos uno de los dos debe estar presente.
 */
return new class extends Migration {

    public function up(): void
    {
        // 1) Agregar columna nullable primero para no romper la tabla.
        Schema::table('inventario_movimientos', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        // 2) Backfill desde la variante existente.
        DB::statement('
            UPDATE inventario_movimientos m
              JOIN producto_variantes v ON m.variante_id = v.id
              SET m.producto_id = v.producto_id
              WHERE m.producto_id IS NULL
        ');

        // 3) Hacer variante_id nullable + agregar CHECK (uno u otro).
        DB::statement('ALTER TABLE inventario_movimientos MODIFY variante_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE inventario_movimientos
              ADD CONSTRAINT chk_invmov_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        // Fix CRÍTICO chaos re-audit · si hay movimientos agregados vivos
        //   (variante_id NULL), el MODIFY NOT NULL rompe MariaDB con "cannot
        //   be null" y deja migrations table marcada como reverted pero la
        //   columna producto_id sigue viva → estado inconsistente.
        //   Prevenimos con guard ruidoso.
        $huerfanos = (int) DB::table('inventario_movimientos')->whereNull('variante_id')->count();
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado · {$huerfanos} movimientos agregados (variante_id NULL) existen. "
                ."Migrar/limpiar antes de revertir, o el kardex quedará corrupto. "
                ."Comando de ayuda: `php artisan tinker` → borra o convierte esos movs."
            );
        }
        DB::statement('ALTER TABLE inventario_movimientos DROP CONSTRAINT IF EXISTS chk_invmov_sujeto');
        DB::statement('ALTER TABLE inventario_movimientos MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        Schema::table('inventario_movimientos', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
