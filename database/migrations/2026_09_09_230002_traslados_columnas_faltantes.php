<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PATRÓN α · columnas que el Action/Modelo Traslado usan pero la migración
 *   base nunca creó (DATOS-M2). Sin esto, enviar() falla:
 *     "Unknown column 'fecha_envio' in 'field list'"
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('traslados_inventario', function (Blueprint $table) {
            if (! Schema::hasColumn('traslados_inventario', 'enviado_por')) {
                $table->foreignId('enviado_por')->nullable()->after('ejecutado_por')->constrained('users');
            }
            if (! Schema::hasColumn('traslados_inventario', 'anulado_por')) {
                $table->foreignId('anulado_por')->nullable()->after('enviado_por')->constrained('users');
            }
            if (! Schema::hasColumn('traslados_inventario', 'fecha_envio')) {
                $table->timestamp('fecha_envio')->nullable()->after('fecha_solicitud');
            }
            if (! Schema::hasColumn('traslados_inventario', 'anulado_at')) {
                $table->timestamp('anulado_at')->nullable()->after('fecha_ejecucion');
            }
            if (! Schema::hasColumn('traslados_inventario', 'motivo_anulacion')) {
                $table->string('motivo_anulacion', 500)->nullable()->after('motivo');
            }
        });
    }

    public function down(): void
    {
        Schema::table('traslados_inventario', function (Blueprint $table) {
            foreach (['enviado_por', 'anulado_por'] as $col) {
                if (Schema::hasColumn('traslados_inventario', $col)) {
                    try { $table->dropForeign(["traslados_inventario_{$col}_foreign"]); } catch (\Throwable $e) {}
                    $table->dropColumn($col);
                }
            }
            foreach (['fecha_envio', 'anulado_at', 'motivo_anulacion'] as $col) {
                if (Schema::hasColumn('traslados_inventario', $col)) $table->dropColumn($col);
            }
        });
    }
};
