<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro de MÉTODOS DE PAGO (efectivo, transferencia, consignación,
 * cruce de cuentas, NEC, tarjeta, Nequi, Daviplata…).
 *
 * Antes los métodos estaban hardcodeados y triplicados en FacturaVenta/PagoVenta,
 * sin consignación/cruce/NEC. Este maestro los vuelve gestionables por el área
 * contable y define, por método, qué datos exige (referencia/banco/comprobante)
 * y a qué cuenta PUC afecta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique()->comment('Ej: efectivo, transferencia, consignacion, cruce_cuentas, nec');
            $table->string('nombre', 60);
            $table->string('tipo', 20)->default('otro')->comment('efectivo|transferencia|consignacion|cruce|nec|tarjeta|billetera|otro');
            $table->boolean('requiere_referencia')->default(false)->comment('Nº de transacción / comprobante');
            $table->boolean('requiere_banco')->default(false);
            $table->boolean('requiere_comprobante')->default(false)->comment('Exige adjuntar soporte al pagar');
            $table->string('cuenta_puc', 20)->nullable()->comment('Cuenta de caja/banco que afecta (Plan de cuentas)');
            $table->boolean('activo')->default(true);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
