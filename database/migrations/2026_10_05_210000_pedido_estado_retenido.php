<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * LOG-J2 · Enganche cartera al "Enviar pedido".
 *
 * Cuando el vendedor envía el carrito del Portal B2B, hoy el pedido pasa
 * directo a `enviado` y gerencia tiene que leer facturas vencidas a mano
 * para decidir. Agregamos el estado `retenido` para que el ERP marque el
 * pedido automáticamente cuando el semáforo de cartera detecta mora,
 * facturas vencidas o cupo excedido. Gerencia lo libera (→ enviado) o lo
 * rechaza en la bandeja de autorización (LOG-J3).
 *
 * motivo_retencion guarda la razón corta para que gerencia la vea sin
 * recalcular el semáforo.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pedidos_cliente MODIFY estado ENUM('borrador','enviado','retenido','aprobado','rechazado','facturado','despachado','anulado') NOT NULL DEFAULT 'enviado'");
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->string('motivo_retencion', 300)->nullable()->after('motivo_rechazo');
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE pedidos_cliente SET estado='enviado' WHERE estado='retenido'");
        DB::statement("ALTER TABLE pedidos_cliente MODIFY estado ENUM('borrador','enviado','aprobado','rechazado','facturado','despachado','anulado') NOT NULL DEFAULT 'enviado'");
        Schema::table('pedidos_cliente', function (Blueprint $t) {
            $t->dropColumn('motivo_retencion');
        });
    }
};
