<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Comprobante/soporte del pago (trazabilidad pedida en la reunión: "que se
 * registre o se suba el comprobante"). Multi-archivo en JSON, igual que
 * conciliaciones_bancarias.adjuntos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_venta', function (Blueprint $table) {
            $table->json('adjuntos')->nullable()->after('banco')->comment('Rutas de comprobantes subidos');
        });
    }

    public function down(): void
    {
        Schema::table('pagos_venta', function (Blueprint $table) {
            $table->dropColumn('adjuntos');
        });
    }
};
