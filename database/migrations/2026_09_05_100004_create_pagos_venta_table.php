<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pagos aplicados a facturas de venta.
 * Cada pago puede aplicarse a UNA factura (para pagos parciales el cliente hace varios).
 * Clasificación de diferencia sigue TO-BE Contabilidad P2:
 *   - Cliente paga total esperado
 *   - Descuento pronto pago (dentro del plazo)
 *   - Flete asumido por GB
 *   - Descuento fuera de plazo (revisión)
 *   - Pago no identificado (consignaciones por aclarar)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pagos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->nullable()->constrained('facturas_venta');
            $table->foreignId('contacto_id')->nullable()->constrained('contactos');
            $table->date('fecha');
            $table->decimal('monto_recibido', 14, 2);
            $table->decimal('monto_aplicado', 14, 2)->default(0)->comment('Lo que efectivamente cierra saldo');
            $table->decimal('diferencia', 14, 2)->default(0)->comment('positivo=descuento aplicado, negativo=sobrepago');
            $table->string('clasificacion_diferencia', 40)->nullable()
                ->comment('descuento_pronto_pago|flete_asumido_gb|descuento_fuera_plazo|saldo_pendiente|no_identificado');
            $table->string('medio_pago', 30)->default('transferencia')
                ->comment('transferencia|efectivo|tarjeta|nequi|daviplata|otro');
            $table->string('referencia', 60)->nullable();
            $table->string('banco', 60)->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha');
            $table->index('clasificacion_diferencia');
        });
    }

    public function down(): void { Schema::dropIfExists('pagos_venta'); }
};
