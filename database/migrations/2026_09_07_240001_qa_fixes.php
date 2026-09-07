<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint QA-A · fixes de auditor consolidados:
 * - `emitiendo_at` en facturas_venta (bandera anti doble-click al emitir DIAN)
 * - Índice compuesto (estado, fin_at) en empaques_registro
 * - Índice (user_id, created_at) en notificaciones_erp
 * - Exclusión de siigo_response en Audits vía columna dedicada (mover metadata sensible fuera)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $table) {
            if (! Schema::hasColumn('facturas_venta', 'emitiendo_at')) {
                $table->timestamp('emitiendo_at')->nullable()->after('emitida_at')
                    ->comment('Bandera anti-carrera: si !=null en <60s, hay una emisión en curso.');
            }
        });

        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->index(['estado', 'fin_at'], 'idx_empaque_estado_fin');
        });

        Schema::table('notificaciones_erp', function (Blueprint $table) {
            $table->index(['user_id', 'created_at'], 'idx_notif_user_created');
        });
    }

    public function down(): void
    {
        Schema::table('empaques_registro', function (Blueprint $table) {
            $table->dropIndex('idx_empaque_estado_fin');
        });
        Schema::table('notificaciones_erp', function (Blueprint $table) {
            $table->dropIndex('idx_notif_user_created');
        });
        Schema::table('facturas_venta', function (Blueprint $table) {
            $table->dropColumn('emitiendo_at');
        });
    }
};
