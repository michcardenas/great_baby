<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('facturas_venta', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('Consecutivo interno GB, ej: FV-000001');
            $table->foreignId('contacto_id')->constrained('contactos');
            $table->date('fecha_emision');
            $table->date('fecha_vencimiento');

            // Estado del ciclo (aparte del estado DIAN)
            $table->string('estado', 20)->default('pendiente')
                ->comment('borrador|pendiente|pagada|abonada|vencida|anulada');

            // Valores
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('descuento', 14, 2)->default(0);
            $table->decimal('impuestos', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('saldo', 14, 2)->default(0)->comment('total - pagos aplicados');

            // Facturación electrónica (opcional — puede ir a ARI luego)
            $table->string('ari_factura_id', 60)->nullable();
            $table->string('cufe', 200)->nullable();
            $table->timestamp('ari_enviada_at')->nullable();

            // Origen: puede venir de Dropi (remisión), pedido B2B, o manual
            $table->string('origen_type', 100)->nullable();
            $table->unsignedBigInteger('origen_id')->nullable();

            $table->foreignId('vendedor_id')->nullable()->constrained('users');
            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_vencimiento']);
            $table->index('contacto_id');
            $table->index(['origen_type', 'origen_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('facturas_venta'); }
};
