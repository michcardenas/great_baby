<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §5 Diseño Dropi — 2 cortes por día (~350-400 pedidos por corte).
 * Un pedido pertenece a un corte y hereda su fecha operativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_cortes', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->tinyInteger('numero')->comment('1 o 2 (2 cortes/día)');
            $table->string('estado', 20)->default('abierto')->comment('abierto|alistando|empacando|despachado|cerrado');
            $table->unsignedInteger('pedidos_totales')->default(0);
            $table->unsignedInteger('pedidos_pendientes_inv')->default(0);
            $table->unsignedInteger('pedidos_despachados')->default(0);
            $table->foreignId('cerrado_por')->nullable()->constrained('users');
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();

            $table->unique(['fecha', 'numero']);
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_cortes');
    }
};
