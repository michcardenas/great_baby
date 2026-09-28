<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 4 · G.1 · Campos SIIGO en productos + variantes.
 *
 * Basado en la doc oficial SIIGO Ilimitada · Kardex → Referencias:
 * https://ilimitada.portaldeclientes.siigo.com/archivos-kardex-referencias/
 *
 * Todo con default null · idempotente (Schema::hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function ($table) {
            // Jerarquía SIIGO (Línea/Grupo/Subgrupo/Clase).
            if (! Schema::hasColumn('productos', 'linea_id')) $table->foreignId('linea_id')->nullable()->constrained('catalogo_lineas')->nullOnDelete();
            if (! Schema::hasColumn('productos', 'grupo_id')) $table->foreignId('grupo_id')->nullable()->constrained('catalogo_grupos')->nullOnDelete();
            if (! Schema::hasColumn('productos', 'subgrupo_id')) $table->foreignId('subgrupo_id')->nullable()->constrained('catalogo_subgrupos')->nullOnDelete();
            if (! Schema::hasColumn('productos', 'clase_id')) $table->foreignId('clase_id')->nullable()->constrained('catalogo_clases')->nullOnDelete();

            // Aduana + compras internacionales.
            if (! Schema::hasColumn('productos', 'posicion_arancelaria')) $table->string('posicion_arancelaria', 20)->nullable();

            // Unidad de compra distinta de venta + factor conversión.
            if (! Schema::hasColumn('productos', 'unidad_compra_id')) $table->foreignId('unidad_compra_id')->nullable()->constrained('unidades_medida')->nullOnDelete();
            if (! Schema::hasColumn('productos', 'factor_conversion')) $table->decimal('factor_conversion', 12, 4)->nullable();

            // Control de precios y reposición.
            if (! Schema::hasColumn('productos', 'rentabilidad_pct')) $table->decimal('rentabilidad_pct', 6, 2)->nullable();
            if (! Schema::hasColumn('productos', 'reposicion_max_dias')) $table->unsignedSmallInteger('reposicion_max_dias')->nullable();
            if (! Schema::hasColumn('productos', 'descuento_default_pct')) $table->decimal('descuento_default_pct', 6, 2)->nullable();

            // Descripciones extendidas.
            if (! Schema::hasColumn('productos', 'descripcion_ampliada')) $table->text('descripcion_ampliada')->nullable();
            if (! Schema::hasColumn('productos', 'ficha_tecnica')) $table->text('ficha_tecnica')->nullable();

            // Flags SIIGO.
            if (! Schema::hasColumn('productos', 'proteger_precio')) $table->boolean('proteger_precio')->default(false);
            if (! Schema::hasColumn('productos', 'maneja_lotes')) $table->boolean('maneja_lotes')->default(false);
            if (! Schema::hasColumn('productos', 'maneja_seriales')) $table->boolean('maneja_seriales')->default(false);
            if (! Schema::hasColumn('productos', 'es_estadistico')) $table->boolean('es_estadistico')->default(false);

            // NIIF.
            if (! Schema::hasColumn('productos', 'valor_gasto_venta_niif')) $table->decimal('valor_gasto_venta_niif', 14, 2)->nullable();
            if (! Schema::hasColumn('productos', 'valor_neto_realizable_niif')) $table->decimal('valor_neto_realizable_niif', 14, 2)->nullable();
        });

        Schema::table('producto_variantes', function ($table) {
            if (! Schema::hasColumn('producto_variantes', 'comision_pct')) $table->decimal('comision_pct', 6, 2)->nullable();
            if (! Schema::hasColumn('producto_variantes', 'precio_min')) $table->decimal('precio_min', 14, 2)->nullable();
            if (! Schema::hasColumn('producto_variantes', 'precio_max')) $table->decimal('precio_max', 14, 2)->nullable();
            if (! Schema::hasColumn('producto_variantes', 'ubicacion_bodega')) $table->string('ubicacion_bodega', 60)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function ($table) {
            foreach (['comision_pct','precio_min','precio_max','ubicacion_bodega'] as $c) {
                if (Schema::hasColumn('producto_variantes', $c)) $table->dropColumn($c);
            }
        });

        Schema::table('productos', function ($table) {
            foreach (['linea_id','grupo_id','subgrupo_id','clase_id','unidad_compra_id'] as $fk) {
                if (Schema::hasColumn('productos', $fk)) $table->dropConstrainedForeignId($fk);
            }
            foreach ([
                'posicion_arancelaria','factor_conversion','rentabilidad_pct','reposicion_max_dias',
                'descuento_default_pct','descripcion_ampliada','ficha_tecnica','proteger_precio',
                'maneja_lotes','maneja_seriales','es_estadistico','valor_gasto_venta_niif','valor_neto_realizable_niif',
            ] as $c) {
                if (Schema::hasColumn('productos', $c)) $table->dropColumn($c);
            }
        });
    }
};
