<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras_recepcion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recepcion_id')->constrained('compras_recepciones')->cascadeOnDelete();
            $table->foreignId('orden_item_id')->constrained('compra_orden_items');
            $table->foreignId('producto_id')->nullable()->constrained('productos');
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');

            $table->decimal('cantidad_recibida', 12, 3);
            $table->decimal('costo_unit', 14, 4)->comment('Precio OC en moneda base');
            $table->decimal('subtotal', 14, 2);

            $table->string('lote', 60)->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['recepcion_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compras_recepcion_items');
    }
};
