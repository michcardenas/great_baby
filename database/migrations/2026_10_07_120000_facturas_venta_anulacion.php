<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deja constancia de la anulación de una factura.
 *
 * `SiigoEmisionService::anularFacturaEnSiigo()` escribía `anulada_at` desde
 * siempre, pero la columna nunca existió: anular reventaba con "Unknown column"
 * y la factura quedaba viva y cobrable. No se había notado porque nadie había
 * anulado una factura todavía.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (! Schema::hasColumn('facturas_venta', 'anulada_at')) {
                $t->timestamp('anulada_at')->nullable();
            }
            if (! Schema::hasColumn('facturas_venta', 'anulada_por_user_id')) {
                $t->foreignId('anulada_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('facturas_venta', 'motivo_anulacion')) {
                $t->string('motivo_anulacion', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (Schema::hasColumn('facturas_venta', 'motivo_anulacion')) $t->dropColumn('motivo_anulacion');
            if (Schema::hasColumn('facturas_venta', 'anulada_por_user_id')) {
                $t->dropForeign(['anulada_por_user_id']);
                $t->dropColumn('anulada_por_user_id');
            }
            if (Schema::hasColumn('facturas_venta', 'anulada_at')) $t->dropColumn('anulada_at');
        });
    }
};
