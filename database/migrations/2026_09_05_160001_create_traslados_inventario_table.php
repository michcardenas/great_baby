<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('traslados_inventario', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('TRA-YYYY-NNNNNN');
            $table->foreignId('origen_id')->constrained('inventario_ubicaciones');
            $table->foreignId('destino_id')->constrained('inventario_ubicaciones');
            $table->foreignId('solicitado_por')->nullable()->constrained('users');
            $table->foreignId('ejecutado_por')->nullable()->constrained('users');

            $table->date('fecha_solicitud');
            $table->date('fecha_ejecucion')->nullable();

            $table->string('estado', 20)->default('borrador')->index()
                ->comment('borrador | en_transito | recibido | anulado');
            $table->string('motivo', 40)->nullable()
                ->comment('reposicion|averia|correccion|prestamo|otro');

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'fecha_solicitud']);
        });

        Schema::create('traslados_inventario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('traslado_id')->constrained('traslados_inventario')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes');
            $table->integer('cantidad_solicitada');
            $table->integer('cantidad_ejecutada')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['traslado_id', 'variante_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('traslados_inventario_items');
        Schema::dropIfExists('traslados_inventario');
    }
};
