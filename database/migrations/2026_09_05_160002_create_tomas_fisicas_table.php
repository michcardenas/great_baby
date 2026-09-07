<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tomas_fisicas', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique()->comment('TF-YYYY-NNNNNN');
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            $table->foreignId('creada_por')->nullable()->constrained('users');
            $table->foreignId('cerrada_por')->nullable()->constrained('users');

            $table->date('fecha_conteo');
            $table->timestamp('cerrada_at')->nullable();

            $table->string('tipo', 20)->default('total')
                ->comment('total | parcial | ciclico');
            $table->string('alcance', 60)->nullable()
                ->comment('Filtro opcional: marca, categoría, etc.');

            $table->string('estado', 20)->default('borrador')->index()
                ->comment('borrador | en_conteo | ajustada | anulada');

            $table->integer('items_diferentes')->default(0);
            $table->decimal('valor_ajuste', 14, 2)->default(0)
                ->comment('Impacto contable +/- del ajuste');

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tomas_fisicas_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toma_id')->constrained('tomas_fisicas')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes');
            $table->integer('saldo_sistema')->comment('Snapshot al iniciar el conteo');
            $table->integer('cantidad_contada')->nullable();
            $table->integer('diferencia')->default(0);
            $table->decimal('costo_unit', 14, 4)->nullable()->comment('Costo promedio del producto');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['toma_id', 'variante_id']);
            $table->index('diferencia');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tomas_fisicas_items');
        Schema::dropIfExists('tomas_fisicas');
    }
};
