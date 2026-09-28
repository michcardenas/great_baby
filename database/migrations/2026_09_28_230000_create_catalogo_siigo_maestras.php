<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · G.1 · Maestras SIIGO Línea/Grupo/Subgrupo/Clase (jerarquía).
 * Además pivots producto_accesorios y producto_sustitutos (M:M).
 * Todo idempotente (Schema::hasTable).
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('catalogo_lineas')) {
            Schema::create('catalogo_lineas', function (Blueprint $t) {
                $t->id();
                $t->string('codigo', 20)->unique();
                $t->string('nombre', 100);
                $t->boolean('activa')->default(true);
                $t->string('siigo_id', 60)->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('catalogo_grupos')) {
            Schema::create('catalogo_grupos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('linea_id')->constrained('catalogo_lineas')->cascadeOnDelete();
                $t->string('codigo', 20);
                $t->string('nombre', 100);
                $t->boolean('activa')->default(true);
                $t->string('siigo_id', 60)->nullable();
                $t->timestamps();
                $t->unique(['linea_id', 'codigo']);
            });
        }
        if (! Schema::hasTable('catalogo_subgrupos')) {
            Schema::create('catalogo_subgrupos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('grupo_id')->constrained('catalogo_grupos')->cascadeOnDelete();
                $t->string('codigo', 20);
                $t->string('nombre', 100);
                $t->boolean('activa')->default(true);
                $t->timestamps();
                $t->unique(['grupo_id', 'codigo']);
            });
        }
        if (! Schema::hasTable('catalogo_clases')) {
            Schema::create('catalogo_clases', function (Blueprint $t) {
                $t->id();
                $t->foreignId('subgrupo_id')->constrained('catalogo_subgrupos')->cascadeOnDelete();
                $t->string('codigo', 20);
                $t->string('nombre', 100);
                $t->boolean('activa')->default(true);
                $t->timestamps();
                $t->unique(['subgrupo_id', 'codigo']);
            });
        }

        // Pivots M:M · accesorios (al facturar producto A también se factura B)
        // y sustitutos (si A sin stock, ofrecer B).
        if (! Schema::hasTable('producto_accesorios')) {
            Schema::create('producto_accesorios', function (Blueprint $t) {
                $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $t->foreignId('accesorio_id')->constrained('productos')->cascadeOnDelete();
                $t->unsignedSmallInteger('cantidad_default')->default(1);
                $t->timestamps();
                $t->primary(['producto_id', 'accesorio_id']);
            });
        }
        if (! Schema::hasTable('producto_sustitutos')) {
            Schema::create('producto_sustitutos', function (Blueprint $t) {
                $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $t->foreignId('sustituto_id')->constrained('productos')->cascadeOnDelete();
                $t->timestamps();
                $t->primary(['producto_id', 'sustituto_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_sustitutos');
        Schema::dropIfExists('producto_accesorios');
        Schema::dropIfExists('catalogo_clases');
        Schema::dropIfExists('catalogo_subgrupos');
        Schema::dropIfExists('catalogo_grupos');
        Schema::dropIfExists('catalogo_lineas');
    }
};
