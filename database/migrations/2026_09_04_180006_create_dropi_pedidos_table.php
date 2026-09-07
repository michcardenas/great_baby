<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §2 Diseño Dropi — El "estado integral del pedido" que hoy no existe en ningún sistema.
 * La GUÍA es el identificador principal que amarra pedido, inventario, entrega, devolución y pago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_pedidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('corte_id')->constrained('dropi_cortes');

            // Identificadores externos (Dropi)
            $table->string('guia', 40)->unique()->comment('§11 — identificador principal, único en el sistema');
            $table->string('dropi_orden_id', 60)->nullable()->index();
            $table->string('transportadora', 40)->nullable();
            $table->string('tienda', 80)->nullable();
            $table->string('tienda_id', 40)->nullable()->comment('Reservado por si hay multi-tienda futuro');

            // Vendedor dropshipping
            $table->string('vendedor_nombre', 120)->nullable();
            $table->string('vendedor_identificacion', 30)->nullable();
            $table->boolean('requiere_factura_b2b')->default(false)->comment('§21 sondeo B2B en curso');

            // Cliente final (info del envío, no CxC)
            $table->string('cliente_nombre', 120);
            $table->string('cliente_doc', 30)->nullable();
            $table->string('cliente_telefono', 30)->nullable();
            $table->string('cliente_direccion', 200)->nullable();
            $table->string('cliente_ciudad', 80)->nullable();
            $table->string('cliente_depto', 80)->nullable();

            // Ciclo de vida — estado integral
            $table->string('estado', 30)->default('pending')->comment('EstadoPedidoDropi enum');
            $table->timestamp('despachado_at')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamp('devuelto_at')->nullable();
            $table->timestamp('pagado_at')->nullable();

            // Valores — §15/§21: SOLO valor proveedor cuenta para conciliación y facturación
            $table->decimal('monto_esperado_proveedor', 12, 2)->comment('Precio catálogo GB × cantidad');
            $table->decimal('monto_cliente_final', 12, 2)->nullable()->comment('Total de la guía (informativo)');
            $table->decimal('ganancia_vendedor', 12, 2)->nullable();
            $table->decimal('flete_transportadora', 12, 2)->nullable();

            // Facturación
            $table->unsignedBigInteger('remision_interna_id')->nullable()->comment('FK a dropi_remisiones cuando exista');
            $table->string('ari_factura_id', 60)->nullable()->comment('ID de la factura en ARI');
            $table->timestamp('ari_enviado_at')->nullable();
            $table->string('nota_credito_id', 60)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['corte_id', 'estado']);
            $table->index('estado');
            $table->index('despachado_at');
            $table->index('pagado_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_pedidos');
    }
};
