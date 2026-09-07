<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kardex — cada movimiento de stock queda registrado con su referencia origen.
 * El saldo por (variante, ubicacion) se calcula sumando el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            $table->string('tipo', 20)->comment('ingreso|egreso|reserva|liberacion|traslado|ajuste');
            $table->integer('cantidad')->comment('Positivo o negativo según tipo');
            $table->string('referencia_tipo', 40)->nullable()->comment('dropi_pedido|devolucion|garantia|traslado|inventario_fisico');
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->text('notas')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['variante_id', 'ubicacion_id', 'created_at'], 'idx_kardex');
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_movimientos');
    }
};
