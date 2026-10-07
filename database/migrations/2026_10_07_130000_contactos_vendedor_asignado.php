<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cartera de clientes por vendedor.
 *
 * `pedidos_cliente` ya guardaba `vendedor_id` (quién levantó el pedido), pero el
 * cliente no tenía dueño. Con 8 vendedores en la calle eso significa que
 * cualquiera podía abrir `/app/vendedor/pedido-nuevo/{cliente-de-otro}`,
 * levantarle el pedido y quedarse la comisión — sin que el sistema lo notara.
 *
 * Queda nullable a propósito: hoy ningún cliente tiene vendedor asignado, así
 * que arrancar en fail-closed dejaría a los 8 vendedores sin poder trabajar el
 * primer día. NULL = cliente libre, lo toma el primero que le venda; con
 * vendedor = sólo ese vendedor (y gerencia) lo ve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            if (! Schema::hasColumn('contactos', 'vendedor_id')) {
                $t->foreignId('vendedor_id')->nullable()->after('lista_precios_id')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            if (Schema::hasColumn('contactos', 'vendedor_id')) {
                $t->dropConstrainedForeignId('vendedor_id');
            }
        });
    }
};
