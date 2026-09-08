<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reglas de negocio configurables por Aracely / admin.
 * Un solo diccionario key/value con tipo y grupo, cacheado.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('reglas_negocio', function (Blueprint $t) {
            $t->id();
            $t->string('clave', 100)->unique();
            $t->string('grupo', 40)->index();  // empaque, dashboard, cartera, dropi, contable, catalogo
            $t->string('tipo', 20);            // string, int, float, bool, json
            $t->text('valor')->nullable();
            $t->string('etiqueta', 200);       // "Alerta pendientes por empacar (>N)"
            $t->text('descripcion')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reglas_negocio');
    }
};
