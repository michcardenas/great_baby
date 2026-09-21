<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el nombre y la variación del producto en el ítem del pedido Dropi
 * (vienen en el export "Órdenes con Productos"). Sirve para reportes/dashboard
 * (ej. top de productos por unidades) sin depender del match con el catálogo GB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            $table->string('producto_nombre', 150)->nullable()->after('sku_dropi');
            $table->string('variacion', 80)->nullable()->after('producto_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('dropi_pedido_items', function (Blueprint $table) {
            $table->dropColumn(['producto_nombre', 'variacion']);
        });
    }
};
