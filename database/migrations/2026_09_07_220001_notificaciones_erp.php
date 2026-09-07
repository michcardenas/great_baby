<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistema de notificaciones internas (bell icon del topbar).
 * Reutilizamos Laravel Notifications si algún día se necesita broadcasting,
 * pero por ahora tabla propia porque queremos historial navegable y filtros.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones_erp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete()
                ->comment('null = broadcast a todos los admins/Aracely');
            $table->string('tipo', 40)->comment('factura_vencida|timbrado_ok|timbrado_rechazado|oc_llegada|dropi_pago|stock_bajo|...');
            $table->string('titulo', 200);
            $table->text('mensaje')->nullable();
            $table->string('url', 500)->nullable()->comment('destino del click');
            $table->string('icono', 40)->nullable()->default('heroicon-o-bell');
            $table->string('color', 20)->default('gray')->comment('success|warning|danger|info|gray');
            $table->timestamp('leida_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'leida_at']);
            $table->index(['tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones_erp');
    }
};
