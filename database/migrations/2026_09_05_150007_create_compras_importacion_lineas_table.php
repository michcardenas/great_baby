<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_importacion_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('importacion_id')->constrained('compras_importaciones')->cascadeOnDelete();
            $table->foreignId('orden_item_id')->constrained('compra_orden_items');
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');

            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_fob_unit', 14, 4);
            $table->decimal('costo_fob_total', 14, 2);

            $table->decimal('gasto_prorrateado', 14, 2)->default(0);
            $table->decimal('costo_final_unit', 14, 4)->default(0);
            $table->decimal('costo_final_total', 14, 2)->default(0);

            $table->timestamps();

            $table->index(['importacion_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_importacion_lineas');
    }
};
