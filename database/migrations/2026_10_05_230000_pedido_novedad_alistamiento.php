<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J5-fix · El pedido con novedad NO puede fluir al despacho.
 *
 * El flujo original dejaba pasar al siguiente paso cualquier pedido que el
 * alistador diera por terminado, aunque hubiera marcado "faltó 1 unidad" o
 * "producto averiado" en las notas. Eso justamente es lo que Don Jorge no
 * quiere: que la mercancía mala llegue al empaque y de ahí salga a cliente.
 *
 * Agregamos una bandera dura (`alistado_con_novedad`) + tipo de novedad para
 * que:
 *   1. La UI mande el pedido a una columna "Con novedad" en vez de "Listos".
 *   2. El gate de despacho (LOG-J7) rechace mientras no esté resuelta.
 *   3. Se dispare una notificación a Gerencia.
 *
 * `resuelta_at`/`resuelta_por_id` cierran la novedad cuando Compras repone,
 * se arma un cambio o se decide seguir sin el faltante.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->boolean('alistado_con_novedad')->default(false)->after('alistado_notas');
            // Enum ligero vía string + validación en el controller — más fácil
            //   de extender que un ALTER TABLE si Jorge pide tipos nuevos.
            $t->string('alistado_tipo_novedad', 20)->nullable()->after('alistado_con_novedad');
            $t->timestamp('novedad_resuelta_at')->nullable()->after('alistado_tipo_novedad');
            $t->foreignId('novedad_resuelta_por_id')->nullable()->after('novedad_resuelta_at')
                ->constrained('users')->nullOnDelete();
            $t->string('novedad_resolucion', 300)->nullable()->after('novedad_resuelta_por_id');

            // Índice para que el gate de despacho resuelva "¿tiene novedad abierta?" rápido.
            $t->index(['alistado_con_novedad', 'novedad_resuelta_at'], 'idx_novedad_abierta');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropIndex('idx_novedad_abierta');
            $t->dropConstrainedForeignId('novedad_resuelta_por_id');
            $t->dropColumn([
                'alistado_con_novedad', 'alistado_tipo_novedad',
                'novedad_resuelta_at', 'novedad_resolucion',
            ]);
        });
    }
};
