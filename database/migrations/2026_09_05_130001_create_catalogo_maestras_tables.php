<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Marcas (Ej: Andina, Kids, Baby Star)
        Schema::create('marcas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->foreignId('proveedor_id')->nullable()->constrained('contactos');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Categorías (con jerarquía: padre → hijo)
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('padre_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('cuenta_puc_ingreso', 20)->nullable()->comment('Ej: 4135 para ropa');
            $table->string('cuenta_puc_costo', 20)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Colecciones / temporadas
        Schema::create('colecciones', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 100);
            $table->date('fecha_lanzamiento')->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Colores (Ej: 02=Azul con hex #1e40af)
        Schema::create('colores', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique()->comment('Ej: 02');
            $table->string('nombre', 40);
            $table->string('hex', 7)->nullable()->comment('#RRGGBB');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Diseños (Ej: LEÓ=León)
        Schema::create('disenos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 60);
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Tallas (Ej: 6M=6 meses, orden=1)
        Schema::create('tallas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 40);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Unidades de medida (unidad, kg, ml, cm, pack)
        Schema::create('unidades_medida', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 10)->unique();
            $table->string('nombre', 40);
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        // Impuestos (IVA 19%, IVA 5%, Excluido, Retención fuente 2.5%)
        Schema::create('impuestos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 60);
            $table->string('tipo', 20)->comment('iva|retencion_fuente|retencion_iva|retencion_ica|otro');
            $table->decimal('porcentaje', 6, 3);
            $table->string('cuenta_puc', 20)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Listas de precios (Mayorista, Detal, Dropi, Distribuidor)
        Schema::create('listas_precios', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->string('nombre', 100);
            $table->string('canal', 30)->comment('mayorista|detal|dropi|distribuidor|especial');
            $table->boolean('activa')->default(true);
            $table->boolean('predeterminada')->default(false);
            $table->timestamps();
        });

        // Precios por variante × lista (con vigencia)
        Schema::create('precios_variante', function (Blueprint $table) {
            $table->id();
            $table->foreignId('variante_id')->constrained('producto_variantes')->cascadeOnDelete();
            $table->foreignId('lista_id')->constrained('listas_precios')->cascadeOnDelete();
            $table->decimal('precio', 12, 2);
            $table->date('vigente_desde');
            $table->date('vigente_hasta')->nullable();
            $table->timestamps();

            $table->index(['variante_id', 'lista_id', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('precios_variante');
        Schema::dropIfExists('listas_precios');
        Schema::dropIfExists('impuestos');
        Schema::dropIfExists('unidades_medida');
        Schema::dropIfExists('tallas');
        Schema::dropIfExists('disenos');
        Schema::dropIfExists('colores');
        Schema::dropIfExists('colecciones');
        Schema::dropIfExists('categorias');
        Schema::dropIfExists('marcas');
    }
};
