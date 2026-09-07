<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §19 Diseño Dropi — Detección de sanciones por diferencia de precio o categoría explícita.
 * GB no puede disputar sanciones (§19): el sistema solo REPORTA, no gestiona disputa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_sanciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos');
            $table->string('tipo', 30)->comment('categoria_explicita | diferencia_precio');
            $table->decimal('monto_esperado', 12, 2);
            $table->decimal('monto_recibido', 12, 2);
            $table->decimal('diferencia', 12, 2);
            $table->timestamp('detectada_at');
            $table->timestamps();

            $table->index('detectada_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_sanciones');
    }
};
