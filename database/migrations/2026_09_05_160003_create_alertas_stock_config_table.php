<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertas_stock_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('ubicacion_id')->nullable()->constrained('inventario_ubicaciones')
                ->comment('Null = aplica en cualquier bodega');

            $table->integer('stock_minimo')->default(0);
            $table->integer('stock_maximo')->nullable();
            $table->integer('punto_reorden')->nullable()
                ->comment('Cuando saldo <= este valor, sugerir OC');
            $table->integer('cantidad_reorden')->nullable()
                ->comment('Cuánto pedir cuando dispara reorden');

            $table->boolean('notificar_email')->default(true);
            $table->boolean('notificar_whatsapp')->default(false);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['variante_id', 'ubicacion_id']);
        });

        Schema::create('alertas_stock_disparadas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained('alertas_stock_config')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes');
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            $table->string('tipo', 20)->comment('minimo | maximo | reorden');
            $table->integer('saldo_al_disparar');
            $table->boolean('resuelta')->default(false);
            $table->timestamp('resuelta_at')->nullable();
            $table->foreignId('resuelta_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['resuelta', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertas_stock_disparadas');
        Schema::dropIfExists('alertas_stock_config');
    }
};
