<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factura_venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('facturas_venta')->cascadeOnDelete();
            $table->foreignId('variante_id')->nullable()->constrained('producto_variantes');
            $table->string('descripcion', 200);
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->decimal('precio_unit', 12, 2);
            $table->decimal('descuento_pct', 5, 2)->default(0);
            $table->decimal('impuesto_pct', 5, 2)->default(0);
            $table->decimal('subtotal', 14, 2);
            $table->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('factura_venta_items'); }
};
