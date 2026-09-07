<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_lists', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 30)->unique();
            $table->foreignId('ubicacion_id')->constrained('inventario_ubicaciones');
            $table->foreignId('alistador_id')->nullable()->constrained('users');
            $table->string('canal', 20)->default('dropi')
                ->comment('dropi | b2b | mixta');
            $table->string('estado', 20)->default('pendiente')->index()
                ->comment('pendiente | en_alistamiento | terminada | anulada');
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('terminada_at')->nullable();
            $table->integer('items_total')->default(0);
            $table->integer('items_pickados')->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();
        });

        Schema::create('picking_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->foreignId('variante_id')->constrained('producto_variantes');
            $table->string('origen_type', 100);
            $table->unsignedBigInteger('origen_id');
            $table->integer('cantidad_requerida');
            $table->integer('cantidad_pickada')->default(0);
            $table->timestamp('pickado_at')->nullable();
            $table->foreignId('pickado_por')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['picking_id', 'variante_id']);
            $table->index(['origen_type', 'origen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_items');
        Schema::dropIfExists('picking_lists');
    }
};
