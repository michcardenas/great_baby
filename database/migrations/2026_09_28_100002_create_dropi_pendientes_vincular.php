<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 (Siigo sync) · cola de "productos Dropi por vincular".
 *
 * Cuando llega un pedido Dropi con un SKU/variación que el ERP NO reconoce,
 * el item entra sin variante (variante_id NULL) y en paralelo se registra
 * aquí una fila que dice "oye, este SKU está apareciendo, alguien debe
 * decidir a qué producto del ERP corresponde".
 *
 * Aracely (o quien tenga el rol) entra a la página `/admin/vincular-productos-dropi`
 * y para cada pendiente elige el producto/variante del ERP correcta.
 * Al confirmar:
 *   1. Se llena `variantes.dropi_variacion_id` de la variante elegida.
 *   2. Se llena `productos.dropi_sku` del producto padre si estaba vacío.
 *   3. Se marca la fila como resuelta (vinculado_at + variante_id + user).
 *   4. BACKFILL: los `dropi_pedido_items` con ese SKU pendientes reciben
 *      su `variante_id` retroactivamente → sus pedidos pueden pasar de
 *      "pendiente inventario" a "listo para alistar".
 *
 * Es la puerta de entrada única para nuevos SKUs Dropi. No se crean productos
 * automáticamente para evitar basura en el catálogo.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::create('dropi_pendientes_vincular', function (Blueprint $t) {
            $t->id();
            $t->string('dropi_sku', 100)->comment('SKU raíz del producto en Dropi.');
            // A3 · NOT NULL DEFAULT '' para que participe en el unique compuesto.
            //   MariaDB trata NULLs múltiples como distintos, así que sin default
            //   se podían crear N filas pendientes para el mismo producto sin variación.
            $t->string('dropi_variacion_id', 100)->default('')
              ->comment('ID de variación (Azul-M, etc.). String vacío como sentinel si Dropi no envía.');
            $t->string('nombre_dropi', 255)->nullable()
              ->comment('Nombre tal como llega en el Excel/API de Dropi.');
            $t->string('variacion_texto', 255)->nullable()
              ->comment('Texto libre de la variación tal como aparece en Dropi.');
            $t->unsignedInteger('pedidos_count')->default(0)
              ->comment('Cuántos pedidos Dropi están esperando resolución.');
            $t->timestamp('primer_pedido_at')->nullable()
              ->comment('Cuándo apareció por primera vez este SKU sin resolver.');
            $t->timestamp('ultimo_pedido_at')->nullable()
              ->comment('Cuándo fue la última vez que un pedido trajo este SKU.');

            // Cuando se resuelve
            $t->timestamp('vinculado_at')->nullable()
              ->comment('Cuándo se vinculó al producto ERP. NULL = pendiente por resolver.');
            $t->foreignId('variante_id')->nullable()
              ->constrained('producto_variantes')->nullOnDelete()
              ->comment('Variante ERP a la que se vinculó este SKU Dropi.');
            $t->foreignId('vinculado_por')->nullable()
              ->constrained('users')->nullOnDelete()
              ->comment('Usuario que hizo la vinculación manual.');

            $t->timestamps();

            // Un SKU + variación es único (dos pedidos del mismo item no crean 2 filas).
            $t->unique(['dropi_sku', 'dropi_variacion_id'], 'dpv_sku_variacion_uq');
            $t->index('vinculado_at', 'dpv_vinculado_at_idx');
            $t->index('ultimo_pedido_at', 'dpv_ultimo_pedido_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_pendientes_vincular');
    }
};
