<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · B.3+ · Pagos a proveedor.
 *
 * Cada pago a proveedor dispara CalculadorRetenciones y crea entradas
 * polimórficas en retenciones_aplicadas (origen_type=PagoProveedor).
 * El neto pagado = monto_bruto - iva_ret - retefuente - reteica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_proveedor', function (Blueprint $t) {
            $t->id();
            $t->date('fecha');
            $t->foreignId('contacto_id')->constrained('contactos');
            $t->unsignedBigInteger('orden_compra_id')->nullable();
            $t->unsignedBigInteger('recepcion_id')->nullable();
            $t->decimal('monto_bruto', 15, 2);
            $t->decimal('iva', 15, 2)->default(0);
            $t->decimal('monto_retenciones', 15, 2)->default(0);
            $t->decimal('monto_neto', 15, 2);
            $t->string('concepto_retencion', 60)->default('compras_generales');
            $t->string('ciudad', 100)->nullable();
            $t->boolean('gran_contribuyente')->default(false);
            $t->string('metodo', 30)->default('transferencia'); // transferencia|efectivo|cheque
            $t->string('cuenta_puc_egreso', 20)->default('1110');
            $t->text('observaciones')->nullable();
            $t->string('siigo_voucher_id', 64)->nullable()->unique();
            $t->json('siigo_response')->nullable();
            $t->timestamp('siigo_sync_at')->nullable();
            $t->foreignId('user_id')->constrained('users');
            $t->timestamps();
            $t->softDeletes();
            $t->index('fecha');
            $t->index('contacto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pagos_proveedor');
    }
};
