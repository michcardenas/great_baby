<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->foreignId('marca_id')->nullable()->after('activo')->constrained('marcas');
            $table->foreignId('categoria_id')->nullable()->after('marca_id')->constrained('categorias');
            $table->foreignId('coleccion_id')->nullable()->after('categoria_id')->constrained('colecciones');
            $table->foreignId('unidad_medida_id')->nullable()->after('coleccion_id')->constrained('unidades_medida');
            $table->foreignId('impuesto_id')->nullable()->after('unidad_medida_id')->constrained('impuestos');
            $table->boolean('neto')->default(true)->after('impuesto_id')
                ->comment('TO-BE Contabilidad P2 · true=aplica descuentos/flete, false=protegido');

            // Dimensiones para envío
            $table->decimal('peso_gr', 8, 2)->nullable()->after('neto');
            $table->decimal('alto_cm', 6, 2)->nullable();
            $table->decimal('ancho_cm', 6, 2)->nullable();
            $table->decimal('largo_cm', 6, 2)->nullable();
        });

        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->foreignId('color_id')->nullable()->after('producto_id')->constrained('colores');
            $table->foreignId('diseno_id')->nullable()->after('color_id')->constrained('disenos');
            $table->foreignId('talla_id')->nullable()->after('diseno_id')->constrained('tallas');
            $table->unsignedInteger('stock_minimo')->default(0)->after('codigo_barras');
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('color_id');
            $table->dropConstrainedForeignId('diseno_id');
            $table->dropConstrainedForeignId('talla_id');
            $table->dropColumn('stock_minimo');
        });
        Schema::table('productos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('marca_id');
            $table->dropConstrainedForeignId('categoria_id');
            $table->dropConstrainedForeignId('coleccion_id');
            $table->dropConstrainedForeignId('unidad_medida_id');
            $table->dropConstrainedForeignId('impuesto_id');
            $table->dropColumn(['neto', 'peso_gr', 'alto_cm', 'ancho_cm', 'largo_cm']);
        });
    }
};
