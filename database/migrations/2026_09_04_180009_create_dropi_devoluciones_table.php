<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §12 Diseño Dropi — Devoluciones de PEDIDO COMPLETO (transportadora devuelve la guía).
 * Producto individual con falla se maneja como GARANTÍA (§8), no como devolución.
 * El alistador decide destino del inventario tras verificación física (§12).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_devoluciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos');
            $table->timestamp('recibido_at');
            $table->string('destino_inventario', 30)->comment('reingreso|averia_reparar|averia_baja|baja_total');
            $table->foreignId('decision_por')->constrained('users')->comment('§12 alistador decide');
            $table->boolean('genero_nota_credito')->default(false);
            $table->string('nota_credito_ari_id', 60)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('destino_inventario');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_devoluciones');
    }
};
