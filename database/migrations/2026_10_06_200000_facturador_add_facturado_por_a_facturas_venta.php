<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rol Facturador · agrega `facturado_por_user_id` a facturas_venta
 *   para rastrear quién presionó el botón "Facturar en SIIGO" (segregación
 *   de funciones y auditoría contable). También `facturado_send_dian` y
 *   `facturado_send_mail` para que el Facturador recuerde qué decisión tomó.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (! Schema::hasColumn('facturas_venta', 'facturado_por_user_id')) {
                $t->foreignId('facturado_por_user_id')->nullable()
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('facturas_venta', 'facturado_send_dian')) {
                $t->boolean('facturado_send_dian')->default(true);
            }
            if (! Schema::hasColumn('facturas_venta', 'facturado_send_mail')) {
                $t->boolean('facturado_send_mail')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (Schema::hasColumn('facturas_venta', 'facturado_send_mail')) $t->dropColumn('facturado_send_mail');
            if (Schema::hasColumn('facturas_venta', 'facturado_send_dian')) $t->dropColumn('facturado_send_dian');
            if (Schema::hasColumn('facturas_venta', 'facturado_por_user_id')) {
                $t->dropForeign(['facturado_por_user_id']);
                $t->dropColumn('facturado_por_user_id');
            }
        });
    }
};
