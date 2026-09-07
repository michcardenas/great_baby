<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservas_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            $table->integer('cantidad');

            $table->string('origen_type', 100)->comment('FacturaVenta | DropiPedido | PedidoB2B');
            $table->unsignedBigInteger('origen_id');

            $table->timestamp('expira_at')->nullable()->comment('Reservas temporales (pedidos web)');
            $table->boolean('activa')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['origen_type', 'origen_id']);
            $table->index(['activa', 'expira_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas_inventario');
    }
};
