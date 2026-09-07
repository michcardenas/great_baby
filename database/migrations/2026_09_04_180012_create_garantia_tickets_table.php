<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8 Diseño Dropi — Garantía: producto individual que llegó dañado o falla.
 * Reserva REACTIVA de stock por ticket (no cantidad fija por producto).
 * Plazo de 72h para dar concepto (mismo plazo que Dropi usa para sus sanciones).
 * Decisión aprobar/rechazar: Aracely o SAC, NO el alistador (§8).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('garantia_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_original_id')->nullable()->constrained('dropi_pedidos');
            $table->foreignId('variante_id')->constrained('producto_variantes');
            $table->unsignedInteger('cantidad')->default(1);
            $table->string('cliente_nombre', 120);
            $table->string('cliente_telefono', 30)->nullable();
            $table->text('descripcion_falla')->nullable();
            $table->string('estado', 30)->default('abierto')->comment('abierto|aprobado|rechazado|reposicion_enviada|cerrado');
            $table->foreignId('creado_por')->nullable()->constrained('users');
            $table->foreignId('decision_por')->nullable()->constrained('users')->comment('§8 Aracely o SAC');
            $table->timestamp('plazo_concepto_at')->comment('created_at + 72h §8');
            $table->timestamp('decision_at')->nullable();
            $table->foreignId('ubicacion_reserva_id')->nullable()->constrained('inventario_ubicaciones')->comment('Reserva reactiva en zona garantía');
            $table->timestamps();
            $table->timestamp('closed_at')->nullable();

            $table->index(['estado', 'plazo_concepto_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('garantia_tickets');
    }
};
