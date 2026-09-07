<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §17, §18 Diseño Dropi — Movimientos de la billetera Dropi.
 * NO se compara contra extracto bancario (§17). Se confía en la wallet.
 * Categorías de gasto (§18): indemnizaciones, fletes garantías, tarjeta, etc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->string('tipo', 20)->comment('TipoMovimientoWallet enum');
            $table->decimal('monto', 12, 2)->comment('+ ingreso a wallet, - egreso');
            $table->foreignId('pedido_id')->nullable()->constrained('dropi_pedidos')->comment('Solo si tipo=pago_guia');
            $table->string('categoria', 40)->nullable()->comment('§18 lista abierta de categorías');
            $table->json('fuente')->nullable()->comment('Payload original de Dropi');
            $table->timestamps();

            $table->index(['fecha', 'tipo']);
            $table->index('categoria');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_wallet_movimientos');
    }
};
