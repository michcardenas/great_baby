<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §6 Diseño Dropi — Cola compartida con bloqueo al abrir para empaque.
 * Permite 2-3 empacadores simultáneos sin que empaquen el mismo pedido.
 * Auto-libera el lock si supera X minutos sin actividad.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropi_alistador_locks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->unique()->constrained('dropi_pedidos')->cascadeOnDelete();
            $table->foreignId('alistador_id')->constrained('users');
            $table->timestamp('locked_at')->useCurrent();
            $table->timestamp('heartbeat_at')->useCurrent()->comment('Actualiza cada X seg; expira si supera timeout');

            $table->index('heartbeat_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropi_alistador_locks');
    }
};
