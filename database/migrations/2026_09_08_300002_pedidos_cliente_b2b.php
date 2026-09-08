<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedidos_cliente', function (Blueprint $t) {
            $t->id();
            $t->string('numero', 20)->unique();
            $t->foreignId('contacto_id')->constrained('contactos');
            $t->foreignId('lista_precios_id')->nullable()->constrained('listas_precios')->nullOnDelete();
            $t->enum('estado', ['borrador', 'enviado', 'aprobado', 'rechazado', 'facturado', 'anulado'])
                ->default('borrador');
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('iva', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->text('notas_cliente')->nullable();
            $t->text('notas_internas')->nullable();
            $t->timestamp('enviado_at')->nullable();
            $t->timestamp('aprobado_at')->nullable();
            $t->timestamp('rechazado_at')->nullable();
            $t->foreignId('facturado_por_id')->nullable()->constrained('users');
            $t->timestamp('facturado_at')->nullable();
            $t->foreignId('factura_id')->nullable()->constrained('facturas_venta')->nullOnDelete();
            $t->string('motivo_rechazo')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['estado', 'created_at']);
            $t->index(['contacto_id', 'estado']);
        });

        Schema::create('pedidos_cliente_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pedido_id')->constrained('pedidos_cliente')->cascadeOnDelete();
            $t->foreignId('variante_id')->constrained('producto_variantes');
            $t->string('sku_snapshot', 60);
            $t->string('descripcion_snapshot');
            $t->unsignedInteger('cantidad');
            $t->decimal('precio_unitario', 14, 2);
            $t->decimal('iva_porcentaje', 5, 2)->default(0);
            $t->decimal('subtotal', 14, 2);
            $t->decimal('iva_valor', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->timestamps();
            $t->index('pedido_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_cliente_items');
        Schema::dropIfExists('pedidos_cliente');
    }
};
