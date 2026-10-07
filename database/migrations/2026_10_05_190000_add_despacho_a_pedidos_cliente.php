<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J7 · Gate duro: no se despacha sin factura/remisión.
 *
 * Hoy el ciclo del pedido B2B termina en 'aprobado' (con factura_id). El
 * paso físico — entrega al transportador — no se registra, por eso la
 * mercancía sale sin huella en el ERP y el inventario queda negativo
 * (observación de Don Jorge, levantamiento 2026-10-05).
 *
 * Añadimos:
 *   - despachado_at + despachado_por_id para huella operativa
 *   - guia_transportadora para el número que da la transportadora
 *   - transportadora como texto corto
 *
 * El estado 'despachado' se añade al enum via el controller (la columna
 * sigue siendo string sin check-constraint, por simetría con los demás
 * estados). El guard que exige factura_id vive en el método Despachar
 * del controller — no en una constraint — porque la regla es de negocio
 * (hay escenarios tipo "remisión en tránsito" que querrán matizar).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->timestamp('despachado_at')->nullable()->after('facturado_at');
            $t->foreignId('despachado_por_id')->nullable()->after('despachado_at')->constrained('users');
            $t->string('guia_transportadora', 60)->nullable()->after('despachado_por_id');
            $t->string('transportadora', 80)->nullable()->after('guia_transportadora');
            $t->index('despachado_at');
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropConstrainedForeignId('despachado_por_id');
            $t->dropColumn(['despachado_at', 'guia_transportadora', 'transportadora']);
        });
    }
};
