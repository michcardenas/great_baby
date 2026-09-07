<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_pedido_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos')->cascadeOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');
            $table->string('sku_dropi', 60)->nullable()->comment('SKU raw como llega de Dropi antes del match');
            $table->unsignedInteger('cantidad');
            $table->decimal('precio_proveedor_unit', 12, 2)->comment('§15/§21 — del catálogo GB');
            $table->foreignId('ubicacion_asignada_id')->nullable()->constrained('inventario_ubicaciones');
            $table->boolean('despachado')->default(false);
            $table->timestamps();

            $table->index('sku_dropi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_pedido_items');
    }
};
