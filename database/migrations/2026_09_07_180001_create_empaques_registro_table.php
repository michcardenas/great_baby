<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de eventos de empaque para métricas del operario:
 * tiempo por pedido, ranking del día, throughput por hora.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empaques_registro', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('dropi_pedidos')->cascadeOnDelete();
            $table->foreignId('operario_id')->constrained('users');
            $table->timestamp('inicio_at');
            $table->timestamp('fin_at')->nullable();
            $table->integer('items_totales')->default(0);
            $table->integer('items_escaneados')->default(0);
            $table->integer('duracion_segundos')->nullable();
            $table->string('estado', 20)->default('en_curso')->comment('en_curso | completado | anulado');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['operario_id', 'inicio_at']);
            $table->index(['estado', 'inicio_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('empaques_registro');
    }
};
