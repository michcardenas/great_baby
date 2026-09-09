<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2 backend M6 · varios hardening:
 *   - F14 · dropi_pedidos.notificado_despacho_at (idempotencia WhatsApp)
 *   - M1  · cliente_nombre DEFAULT 'SIN NOMBRE' (payloads sucios no rompen sync)
 *   - A3  · dropi_sanciones UNIQUE(pedido_id, tipo) (evita duplicados en re-conciliación)
 *   - A4  · dropi_cortes.pedidos_pagados nueva columna con recálculo periódico
 *   - M3  · índices para queries de reporte (kardex, wallet, items)
 *   - M4  · tabla dropi_sync_estado (last_sync_at por endpoint)
 */
return new class extends Migration
{
    public function up(): void
    {
        // F14 · marca idempotencia WhatsApp de despacho.
        Schema::table('dropi_pedidos', function (Blueprint $table) {
            if (! Schema::hasColumn('dropi_pedidos', 'notificado_despacho_at')) {
                $table->timestamp('notificado_despacho_at')->nullable()->after('pagado_at');
                $table->index('notificado_despacho_at');
            }
        });

        // M1 · cliente_nombre siempre puede escribirse (default sentinel).
        // Cambiamos definición de NOT NULL sin default a NOT NULL DEFAULT.
        try {
            DB::statement("ALTER TABLE dropi_pedidos MODIFY cliente_nombre VARCHAR(120) NOT NULL DEFAULT 'SIN NOMBRE'");
        } catch (\Throwable) {
            // SQLite en tests no soporta MODIFY; ignoramos.
        }

        // A3 · dedup sanciones. NULL detectada_at no cuenta como duplicado.
        Schema::table('dropi_sanciones', function (Blueprint $table) {
            try {
                $table->unique(['pedido_id', 'tipo'], 'dropi_sanciones_pedido_tipo_unique');
            } catch (\Throwable) {
                // Si ya duplicados existen, saltamos y arreglamos manualmente.
            }
        });

        // A4 · contador de pagados en el corte (antes solo totales/despachados/pend_inv).
        Schema::table('dropi_cortes', function (Blueprint $table) {
            if (! Schema::hasColumn('dropi_cortes', 'pedidos_pagados')) {
                $table->unsignedInteger('pedidos_pagados')->default(0)->after('pedidos_despachados');
            }
        });

        // M3 · índices para queries de reporte.
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            try { $table->index(['variante_id', 'ubicacion_id', 'tipo'], 'imov_var_ubic_tipo_idx'); } catch (\Throwable) {}
        });
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            try { $table->index('pedido_id', 'dwm_pedido_idx'); } catch (\Throwable) {}
        });
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            try { $table->index('variante_id', 'dpi_variante_idx'); } catch (\Throwable) {}
        });

        // M4 · sync estado incremental (dropi_sync_estado).
        if (! Schema::hasTable('dropi_sync_estado')) {
            Schema::create('dropi_sync_estado', function (Blueprint $table) {
                $table->id();
                $table->string('recurso', 40)->unique(); // 'pedidos', 'wallet'
                $table->timestamp('last_sync_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::table('dropi_pedidos', function (Blueprint $table) {
            if (Schema::hasColumn('dropi_pedidos', 'notificado_despacho_at')) {
                $table->dropIndex(['notificado_despacho_at']);
                $table->dropColumn('notificado_despacho_at');
            }
        });
        Schema::table('dropi_sanciones', function (Blueprint $table) {
            try { $table->dropUnique('dropi_sanciones_pedido_tipo_unique'); } catch (\Throwable) {}
        });
        Schema::table('dropi_cortes', function (Blueprint $table) {
            if (Schema::hasColumn('dropi_cortes', 'pedidos_pagados')) {
                $table->dropColumn('pedidos_pagados');
            }
        });
        Schema::dropIfExists('dropi_sync_estado');
    }
};
