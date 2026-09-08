<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F7: Aracely puede marcar un pedido para excluir de la alerta "en tránsito"
 * (útil cuando sabe que la transportadora atrasó pero no hay incidencia real).
 *
 * Además indexamos devuelto_at (fantasma) para performance del dashboard.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('dropi_pedidos', function (Blueprint $t) {
            if (! Schema::hasColumn('dropi_pedidos', 'ignorar_alerta_transito')) {
                $t->boolean('ignorar_alerta_transito')->default(false)->after('devuelto_at');
                $t->index(['estado', 'devuelto_at'], 'idx_pedido_estado_devuelto');
                $t->index(['estado', 'despachado_at', 'ignorar_alerta_transito'], 'idx_pedido_transito');
            }
        });

        // Fix F2 duplicados en producto_variantes — unique compuesto.
        // Como color_codigo/diseno_codigo/talla pueden ser NULL en MariaDB (que trata NULL
        // como distinct en UNIQUE), agregamos columnas generadas que reemplazan NULL con '~'
        // (carácter fuera del set esperado) para forzar uniqueness real.
        if (Schema::hasTable('producto_variantes') && ! Schema::hasColumn('producto_variantes', 'combo_key')) {
            // DEDUP previo: keep max(id) por combo, resto se marca (soft-delete si aplica) o borra.
            DB::statement("
                DELETE FROM producto_variantes
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT MAX(id) as id
                        FROM producto_variantes
                        GROUP BY producto_id, COALESCE(color_codigo,'~'), COALESCE(diseno_codigo,'~'), COALESCE(talla,'~')
                    ) t
                )
            ");

            Schema::table('producto_variantes', function (Blueprint $t) {
                $t->string('combo_key', 200)
                    ->virtualAs("CONCAT(producto_id, '|', COALESCE(color_codigo,'~'), '|', COALESCE(diseno_codigo,'~'), '|', COALESCE(talla,'~'))")
                    ->nullable();
                $t->unique('combo_key', 'uniq_variante_combo');
            });
        }
    }

    public function down(): void
    {
        Schema::table('dropi_pedidos', function (Blueprint $t) {
            if (Schema::hasColumn('dropi_pedidos', 'ignorar_alerta_transito')) {
                $t->dropIndex('idx_pedido_transito');
                $t->dropIndex('idx_pedido_estado_devuelto');
                $t->dropColumn('ignorar_alerta_transito');
            }
        });
        if (Schema::hasTable('producto_variantes')) {
            Schema::table('producto_variantes', function (Blueprint $t) {
                if (Schema::hasColumn('producto_variantes', 'combo_key')) {
                    $t->dropUnique('uniq_variante_combo');
                    $t->dropColumn('combo_key');
                }
            });
        }
    }
};
