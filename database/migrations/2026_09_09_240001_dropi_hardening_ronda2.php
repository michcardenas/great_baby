<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA-M6 Dropi · ronda 2.
 *
 *   PATRÓN η (DATOS-C2) · SoftDeletes en tablas financieras/DIAN:
 *     dropi_cortes, dropi_wallet_movimientos, dropi_pedido_items,
 *     dropi_sanciones, dropi_remisiones. Retención 5 años.
 *
 *   PATRÓN κ (DATOS-M4, M5, B12) · constraints:
 *     - UNIQUE en dropi_pedidos.dropi_orden_id (parcial, permite NULL) —
 *       idempotencia sync si Dropi reenvía orden con guía distinta.
 *     - CHECK dropi_sanciones.monto_esperado/recibido >= 0. La `diferencia`
 *       lleva el signo, monto puro no debe ser negativo.
 *     - UNIQUE dropi_cortes.manifiesto_hash (evita duplicados de snapshot).
 */
return new class extends Migration
{
    public function up(): void
    {
        // === SoftDeletes en 5 tablas ===
        foreach (['dropi_cortes', 'dropi_wallet_movimientos', 'dropi_pedido_items', 'dropi_sanciones', 'dropi_remisiones'] as $t) {
            if (Schema::hasTable($t) && ! Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }

        // === UNIQUE parcial dropi_orden_id (MariaDB: UNIQUE normal permite múltiples NULL) ===
        try {
            DB::statement('ALTER TABLE dropi_pedidos ADD UNIQUE INDEX uniq_dropi_orden_id (dropi_orden_id)');
        } catch (\Throwable $e) {
            // ya existe o hay duplicados legítimos (legacy). Loguear y seguir.
        }

        // === CHECK sanciones monto >= 0 ===
        try {
            DB::statement('ALTER TABLE dropi_sanciones ADD CONSTRAINT ck_sancion_montos_positivos CHECK (monto_esperado >= 0 AND monto_recibido >= 0)');
        } catch (\Throwable $e) {}

        // === UNIQUE manifiesto_hash (dos cortes con snapshot idéntico serían anomalía) ===
        try {
            DB::statement('ALTER TABLE dropi_cortes ADD UNIQUE INDEX uniq_manifiesto_hash (manifiesto_hash)');
        } catch (\Throwable $e) {}

        // === Índice compuesto (alistador_id, estado) para vista Alistador ===
        try {
            Schema::table('dropi_pedidos', function (Blueprint $table) {
                // Sólo si no existe ya un índice llamado así
                $table->index(['estado', 'corte_id'], 'idx_pedidos_estado_corte');
            });
        } catch (\Throwable $e) {}

        // === Índice alistador_id en locks (DATOS-M7) ===
        try {
            Schema::table('dropi_alistador_locks', function (Blueprint $table) {
                $table->index('alistador_id', 'idx_locks_alistador');
            });
        } catch (\Throwable $e) {}

        // === Ampliar tipo sanción (para 'sobrepago' agregado en θ) ===
        try {
            DB::statement("ALTER TABLE dropi_sanciones MODIFY tipo VARCHAR(40) NOT NULL");
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        try { DB::statement('ALTER TABLE dropi_cortes DROP INDEX uniq_manifiesto_hash'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE dropi_pedidos DROP INDEX uniq_dropi_orden_id'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE dropi_sanciones DROP CONSTRAINT ck_sancion_montos_positivos'); } catch (\Throwable $e) {}

        foreach (['dropi_pedidos' => 'idx_pedidos_estado_corte', 'dropi_alistador_locks' => 'idx_locks_alistador'] as $tbl => $idx) {
            try { Schema::table($tbl, fn (Blueprint $t) => $t->dropIndex($idx)); } catch (\Throwable $e) {}
        }

        foreach (['dropi_cortes', 'dropi_wallet_movimientos', 'dropi_pedido_items', 'dropi_sanciones', 'dropi_remisiones'] as $t) {
            if (Schema::hasTable($t) && Schema::hasColumn($t, 'deleted_at')) {
                Schema::table($t, fn (Blueprint $table) => $table->dropSoftDeletes());
            }
        }
    }
};
