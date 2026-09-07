<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Productos base (referencia del proveedor, ej. "AND2512-79/154").
 * Las variantes (color+diseño+talla) viven en producto_variantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('referencia', 100)->unique()->comment('Código general del proveedor');
            $table->string('nombre');
            $table->string('categoria', 80)->nullable();
            $table->text('descripcion')->nullable();
            $table->decimal('precio_proveedor', 12, 2)->default(0)->comment('Precio catálogo GB — base para conciliación Dropi');
            $table->boolean('activo')->default(true);
            $table->boolean('requiere_talla')->default(false);
            $table->boolean('es_set')->default(false)->comment('§9 sets/kits');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activo', 'categoria']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
