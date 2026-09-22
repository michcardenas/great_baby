<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollback pedido por el cliente · congelar módulo Dropi al estado pre-desglose-dual.
 *
 * Dropea `producto_id` (columna + FK) de `dropi_pedido_items` para que la tabla
 * quede idéntica al schema del commit fb198b2. La migración es idempotente:
 * si `producto_id` no existe (ambiente ya en el estado deseado), no hace nada.
 *
 * Efecto colateral · el importador Excel del cliente (/app/inventario/importar-cliente)
 * y los 134 productos agregados cargados en prod dejan de tener trazabilidad al
 * catálogo desde items Dropi. Este es el trade-off aceptado por el cliente.
 */
return new class extends Migration {

    public function up(): void
    {
        if (Schema::hasColumn('dropi_pedido_items', 'producto_id')) {
            // 1) CHECK constraint que referencia producto_id (existe si MariaDB lo aceptó).
            try {
                DB::statement('ALTER TABLE dropi_pedido_items DROP CONSTRAINT IF EXISTS chk_dropi_pedido_item_sujeto');
            } catch (\Throwable $e) {}
            // 2) FK (Laravel la nombra <tabla>_<columna>_foreign).
            try {
                Schema::table('dropi_pedido_items', function (Blueprint $t) {
                    $t->dropForeign(['producto_id']);
                });
            } catch (\Throwable $e) {}
            // 3) Columna.
            Schema::table('dropi_pedido_items', function (Blueprint $t) {
                $t->dropColumn('producto_id');
            });
        }
    }

    public function down(): void
    {
        // Re-agregar la columna si es necesario reactivar el desglose-dual en Dropi.
        if (! Schema::hasColumn('dropi_pedido_items', 'producto_id')) {
            Schema::table('dropi_pedido_items', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('variante_id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }
    }
};
