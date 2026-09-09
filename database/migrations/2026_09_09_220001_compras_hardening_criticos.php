<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Re-audit M2 Compras · hardening críticos.
 *
 *   PATRÓN K (DATOS-A5) · SoftDeletes en `compras_importacion_gastos`
 *     (retención DIAN 5 años de evidencia de flete/arancel).
 *
 *   PATRÓN B (FUNC-C4) · columnas de anulación estructurada en OC — antes
 *     el motivo se guardaba concatenado en `observaciones` (mutable, sin
 *     timestamp, sin autor).
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('compras_importacion_gastos') && ! Schema::hasColumn('compras_importacion_gastos', 'deleted_at')) {
            Schema::table('compras_importacion_gastos', function (Blueprint $t) {
                $t->softDeletes();
            });
        }
        if (Schema::hasTable('compras_ordenes')) {
            Schema::table('compras_ordenes', function (Blueprint $t) {
                if (! Schema::hasColumn('compras_ordenes', 'anulado_at')) {
                    $t->timestamp('anulado_at')->nullable()->after('aprobado_at');
                }
                if (! Schema::hasColumn('compras_ordenes', 'anulado_por')) {
                    $t->foreignId('anulado_por')->nullable()->after('anulado_at')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('compras_ordenes', 'motivo_anulacion')) {
                    $t->string('motivo_anulacion', 300)->nullable()->after('anulado_por');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('compras_importacion_gastos') && Schema::hasColumn('compras_importacion_gastos', 'deleted_at')) {
            Schema::table('compras_importacion_gastos', fn (Blueprint $t) => $t->dropSoftDeletes());
        }
        if (Schema::hasTable('compras_ordenes')) {
            Schema::table('compras_ordenes', function (Blueprint $t) {
                foreach (['motivo_anulacion', 'anulado_por', 'anulado_at'] as $col) {
                    if (Schema::hasColumn('compras_ordenes', $col)) {
                        if ($col === 'anulado_por') {
                            try { $t->dropForeign(['anulado_por']); } catch (\Throwable) {}
                        }
                        try { $t->dropColumn($col); } catch (\Throwable) {}
                    }
                }
            });
        }
    }
};
