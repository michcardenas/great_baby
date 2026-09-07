<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §7 Diseño Dropi — Ubicaciones con 5 categorías:
 * venta | averia_reparar | averia_baja | garantia | reserva_proveedor
 * La categoría define para qué está disponible, no solo dónde está.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventario_ubicaciones', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique()->comment('R-A-01 (rack-pasillo-nivel) | AVR | GAR | RES-PROV-01');
            $table->string('nombre', 100);
            $table->string('categoria', 30)->comment('venta|averia_reparar|averia_baja|garantia|reserva_proveedor');
            $table->boolean('disponible_para_venta')->default(false);
            $table->boolean('activa')->default(true);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['categoria', 'activa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventario_ubicaciones');
    }
};
