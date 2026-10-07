<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J1 · Vendedor arma pedido "a nombre de" cliente.
 *
 * Hoy el Portal B2B exige que el cliente mismo entre con su correo y arme
 * el carrito. Los 8 vendedores de Jorge visitan comercios puerta a puerta
 * con sus muestras y necesitan capturar el pedido en el momento, usando
 * su propia sesión del ERP pero con el pedido quedando a nombre del
 * cliente visitado. Esta columna guarda al vendedor que levantó el pedido
 * para comisiones, trazabilidad y para que gerencia vea quién movió qué.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->foreignId('vendedor_id')->nullable()->after('contacto_id')
                ->constrained('users')->nullOnDelete();
            $t->index('vendedor_id', 'idx_pedidos_vendedor');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropIndex('idx_pedidos_vendedor');
            $t->dropConstrainedForeignId('vendedor_id');
        });
    }
};
