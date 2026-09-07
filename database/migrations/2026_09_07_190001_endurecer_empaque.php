<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint C · fixes de auditor:
 * - Contador de unidades pickeadas por item (multi-cantidad correcto).
 * - Unique parcial en empaques_registro (un solo pedido en_curso).
 * - Índices en columnas leídas por el dashboard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            if (! Schema::hasColumn('dropi_pedido_items', 'cantidad_pickeada')) {
                $table->unsignedInteger('cantidad_pickeada')->default(0)->after('pickeado_at');
            }
        });

        // MariaDB no soporta partial indexes nativos; usamos índice normal y
        // la unicidad se garantiza vía lockForUpdate + firstOrCreate en la Action.
        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->index(['pedido_id', 'estado'], 'idx_empaque_pedido_estado');
        });

        // Índices para dashboard TorreControl
        Schema::table('dropi_pedidos', function (Blueprint $table) {
            $table->index('pagado_at', 'idx_pedidos_pagado_at');
            $table->index('despachado_at', 'idx_pedidos_despachado_at');
            $table->index('entregado_at', 'idx_pedidos_entregado_at');
            $table->index('devuelto_at', 'idx_pedidos_devuelto_at');
        });

        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->index('fin_at', 'idx_empaque_fin_at');
        });
    }

    public function down(): void
    {
        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->dropIndex('idx_empaque_pedido_estado');
            $table->dropIndex('idx_empaque_fin_at');
        });
        Schema::table('dropi_pedidos', function (Blueprint $table) {
            $table->dropIndex('idx_pedidos_pagado_at');
            $table->dropIndex('idx_pedidos_despachado_at');
            $table->dropIndex('idx_pedidos_entregado_at');
            $table->dropIndex('idx_pedidos_devuelto_at');
        });
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            $table->dropColumn('cantidad_pickeada');
        });
    }
};
