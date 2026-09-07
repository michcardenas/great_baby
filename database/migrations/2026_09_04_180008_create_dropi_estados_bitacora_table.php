<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bitácora de cambios de estado de cada pedido Dropi.
 * Fuente: api|webhook|manual|sistema. Guarda el payload crudo de Dropi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_estados_bitacora', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos')->cascadeOnDelete();
            $table->string('estado_desde', 30)->nullable();
            $table->string('estado_hasta', 30);
            $table->string('fuente', 20)->comment('api|webhook|manual|sistema');
            $table->json('payload')->nullable()->comment('Respuesta cruda de Dropi u observación');
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['pedido_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_estados_bitacora');
    }
};
