<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LOG-J7 · agregar 'despachado' al enum pedidos_cliente.estado.
 *
 * El enum existente no incluía despachado porque antes el ciclo terminaba
 * en facturado. Ahora con el gate duro para despacho se necesita el estado
 * nuevo. Usamos ALTER directo porque Doctrine DBAL no soporta enum.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE pedidos_cliente MODIFY estado ENUM('borrador','enviado','aprobado','rechazado','facturado','despachado','anulado') NOT NULL DEFAULT 'enviado'");
    }

    public function down(): void
    {
        DB::statement("UPDATE pedidos_cliente SET estado='facturado' WHERE estado='despachado'");
        DB::statement("ALTER TABLE pedidos_cliente MODIFY estado ENUM('borrador','enviado','aprobado','rechazado','facturado','anulado') NOT NULL DEFAULT 'enviado'");
    }
};
