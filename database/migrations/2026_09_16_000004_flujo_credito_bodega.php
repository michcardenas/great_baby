<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de pago/crédito en bodega — Fase 2.
 *  - solicitudes_credito.pedido_id: liga la excepción al pedido que la disparó,
 *    para el loop "retener → aprobar Gerencia → facturar".
 *  - facturas_venta.tipo: contado | credito.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->unsignedBigInteger('pedido_id')->nullable()->after('contacto_id')
                ->comment('Pedido B2B que originó la retención (loop de aprobación)');
            $table->index('pedido_id');
        });

        Schema::table('facturas_venta', function (Blueprint $table) {
            $table->string('tipo', 10)->default('contado')->after('estado')
                ->comment('contado | credito');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes_credito', function (Blueprint $table) {
            $table->dropIndex(['pedido_id']);
            $table->dropColumn('pedido_id');
        });
        Schema::table('facturas_venta', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
    }
};
