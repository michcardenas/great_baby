<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F6 · Parche a `pedidos_cliente_items` (Portal B2B).
 * Sin CHECK · invariante en Model + FK + domain layer.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('pedidos_cliente_items', 'producto_id')) {
            Schema::table('pedidos_cliente_items', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('variante_id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE pedidos_cliente_items i
              JOIN producto_variantes v ON i.variante_id = v.id
              SET i.producto_id = v.producto_id
              WHERE i.producto_id IS NULL AND i.variante_id IS NOT NULL
        ');

        $col = collect(DB::select("SHOW COLUMNS FROM pedidos_cliente_items LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE pedidos_cliente_items MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        try {
            DB::statement('ALTER TABLE pedidos_cliente_items DROP CONSTRAINT IF EXISTS chk_pedido_cliente_item_sujeto');
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        $huerfanos = DB::scalar('SELECT COUNT(*) FROM pedidos_cliente_items WHERE variante_id IS NULL');
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado: {$huerfanos} items de pedidos_cliente_items tienen variante_id NULL."
            );
        }
        DB::statement('ALTER TABLE pedidos_cliente_items MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('pedidos_cliente_items', 'producto_id')) {
            Schema::table('pedidos_cliente_items', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
