<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Precio de venta de los productos AGREGADOS, por lista.
 *
 * Un producto agregado es el que no se desglosa por variante: una sola
 * existencia por referencia, colores surtidos. Los 134 que entraron por el
 * Excel del cliente son así.
 *
 * Hasta hoy no se podían vender. El precio de un granular sale de
 * `precios_variante`, pero el de un agregado no tenía dónde vivir: el código
 * buscaba un modelo `PrecioProducto` con `class_exists()` —que siempre daba
 * false— y la línea del pedido se descartaba en silencio. Medido el
 * 2026-10-08: 15 de los 31 productos activos eran inalcanzables para un
 * pedido, sin ningún mensaje que lo explicara.
 *
 * NO se cae al `precio_proveedor` a propósito: ese es el COSTO, y usarlo le
 * vendería al cliente B2B a precio de compra.
 *
 * Espejo exacto de `precios_variante` para que las dos se consulten igual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('precios_producto', function (Blueprint $t) {
            $t->id();
            $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $t->foreignId('lista_id')->constrained('listas_precios')->cascadeOnDelete();
            $t->decimal('precio', 12, 2);
            $t->date('vigente_desde');
            $t->date('vigente_hasta')->nullable();
            $t->timestamps();

            // Un solo precio vigente por producto y lista, igual que en variantes.
            $t->unique(['producto_id', 'lista_id'], 'precios_producto_producto_lista_unique');
            $t->index(['producto_id', 'lista_id', 'vigente_desde'], 'precios_producto_busqueda_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_producto');
    }
};
