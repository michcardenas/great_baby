<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F6 · Desglose dual — parche a `pedidos_cliente_items` (Portal B2B).
 *
 * Un pedido de cliente puede tener items apuntando a variante (granular) o
 * a producto agregado. Hoy el schema exige variante_id NOT NULL — Aracely no
 * puede recibir pedidos B2B de los 134 productos agregados del cliente.
 *
 * Cambios:
 *   - `producto_id` nullable + FK a productos
 *   - `variante_id` → nullable
 *   - CHECK: al menos uno de los dos
 *   - Backfill de producto_id desde variantes existentes
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('pedidos_cliente_items', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('variante_id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE pedidos_cliente_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL
        ');

        DB::statement('ALTER TABLE pedidos_cliente_items MODIFY variante_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE pedidos_cliente_items
              ADD CONSTRAINT chk_pedido_cliente_item_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE pedidos_cliente_items DROP CONSTRAINT IF EXISTS chk_pedido_cliente_item_sujeto');
        $huerfanos = DB::scalar('SELECT COUNT(*) FROM pedidos_cliente_items WHERE variante_id IS NULL');
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado: {$huerfanos} items de pedidos_cliente_items tienen variante_id NULL."
            );
        }
        DB::statement('ALTER TABLE pedidos_cliente_items MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        Schema::table('pedidos_cliente_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
