<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §21 Diseño Dropi — REMISIÓN 1:1 POR PEDIDO (no por lote de corte).
 * Se agrupan en LOTE al enviar a ARI (por corte), pero cada remisión es un documento independiente.
 * Preserva trazabilidad 1:1 para poder aplicar NC a un pedido específico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_remisiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos');
            $table->string('consecutivo', 20)->unique()->comment('Numeración propia GB');
            $table->decimal('valor_proveedor', 12, 2)->comment('SOLO valor proveedor, no total cliente');
            $table->string('ari_lote_id', 60)->nullable()->comment('ID del lote enviado a ARI');
            $table->string('ari_factura_id', 60)->nullable();
            $table->timestamp('enviada_ari_at')->nullable();
            $table->string('cufe', 200)->nullable();
            $table->string('estado_dian', 30)->nullable();
            $table->timestamps();

            $table->index('enviada_ari_at');
            $table->index('ari_lote_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_remisiones');
    }
};
