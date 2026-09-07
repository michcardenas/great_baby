<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compra_orden_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_id')->constrained('compras_ordenes')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');

            $table->string('descripcion', 255);
            $table->decimal('cantidad', 12, 3);
            $table->decimal('cantidad_recibida', 12, 3)->default(0);

            $table->decimal('precio_unit', 14, 4);
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('iva_pct', 5, 2)->default(0);

            $table->decimal('subtotal', 14, 2);
            $table->decimal('iva', 14, 2)->default(0);
            $table->decimal('total', 14, 2);

            $table->decimal('costo_prorrateado_unit', 14, 4)->nullable()
                ->comment('Set por liquidacion importacion');

            $table->timestamps();

            $table->index(['orden_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compra_orden_items');
    }
};
