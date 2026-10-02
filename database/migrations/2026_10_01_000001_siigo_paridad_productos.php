<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SIIGO · Paridad real producto ↔ SIIGO.
 *
 * Agrega los campos que SIIGO acepta en POST/PUT /v1/products pero el ERP no
 * guardaba (los hardcodeaba o no los enviaba). Después del sprint queda
 * paridad 1:1 entre el formulario del ERP y SIIGO Nube.
 *
 * No borra nada · solo agrega. Idempotente (chequea existencia).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            // 6 campos obligatorios/comunes del payload SIIGO que el ERP ignoraba.
            if (! Schema::hasColumn('productos', 'tipo_siigo')) {
                $t->enum('tipo_siigo', ['Product', 'Service', 'ConsumerGood'])
                    ->default('Product')
                    ->comment('SIIGO: type')
                    ->after('siigo_code');
            }
            if (! Schema::hasColumn('productos', 'stock_control')) {
                $t->boolean('stock_control')->default(true)
                    ->comment('SIIGO: lleva kardex en SIIGO Nube')
                    ->after('tipo_siigo');
            }
            if (! Schema::hasColumn('productos', 'tax_classification')) {
                $t->enum('tax_classification', ['Taxed', 'Exempt', 'Excluded'])
                    ->default('Taxed')
                    ->comment('SIIGO: tax_classification')
                    ->after('stock_control');
            }
            if (! Schema::hasColumn('productos', 'tax_included')) {
                $t->boolean('tax_included')->default(false)
                    ->comment('SIIGO: precio incluye IVA')
                    ->after('tax_classification');
            }
            if (! Schema::hasColumn('productos', 'tax_consumption_value')) {
                $t->decimal('tax_consumption_value', 15, 2)->nullable()
                    ->comment('SIIGO: impoconsumo bebidas azucaradas')
                    ->after('tax_included');
            }
            if (! Schema::hasColumn('productos', 'modelo_siigo')) {
                $t->string('modelo_siigo', 100)->nullable()
                    ->comment('SIIGO: additional_fields.model')
                    ->after('tax_consumption_value');
            }
            if (! Schema::hasColumn('productos', 'barcode_padre')) {
                $t->string('barcode_padre', 100)->nullable()
                    ->comment('SIIGO: additional_fields.barcode para productos agregados sin variantes')
                    ->after('modelo_siigo');
            }
            if (! Schema::hasColumn('productos', 'unit_label')) {
                $t->string('unit_label', 50)->default('Unidad')
                    ->comment('SIIGO: unit_label (etiqueta visible, código real va en unidad_medida)')
                    ->after('barcode_padre');
            }
        });

        // Linkear catálogos locales con su equivalente SIIGO (siigo_id).
        if (! Schema::hasColumn('impuestos', 'siigo_id')) {
            Schema::table('impuestos', function (Blueprint $t) {
                $t->unsignedBigInteger('siigo_id')->nullable()->unique()
                    ->comment('ID del impuesto en SIIGO Nube');
            });
        }
        if (! Schema::hasColumn('unidades_medida', 'codigo_unece')) {
            Schema::table('unidades_medida', function (Blueprint $t) {
                $t->string('codigo_unece', 10)->nullable()
                    ->comment('Código UN/ECE Rec20 que acepta SIIGO (ej: 94=Unidad, BX=Caja)');
            });
        }
        if (! Schema::hasColumn('listas_precios', 'siigo_id')) {
            Schema::table('listas_precios', function (Blueprint $t) {
                $t->unsignedBigInteger('siigo_id')->nullable()->unique()
                    ->comment('ID de la lista de precios en SIIGO Nube');
            });
        }
        if (! Schema::hasColumn('marcas', 'siigo_brand_name')) {
            Schema::table('marcas', function (Blueprint $t) {
                $t->string('siigo_brand_name', 100)->nullable()
                    ->comment('Nombre exacto como aparece en SIIGO additional_fields.brand');
            });
        }
        // Categoría local → account_group SIIGO.
        if (Schema::hasTable('categorias') && ! Schema::hasColumn('categorias', 'siigo_account_group_id')) {
            Schema::table('categorias', function (Blueprint $t) {
                $t->unsignedBigInteger('siigo_account_group_id')->nullable()
                    ->comment('ID del account_group en SIIGO Nube · usado directo en PayloadBuilder');
            });
        }

        // Tabla pivot producto ↔ impuestos (SIIGO acepta N impuestos por producto).
        if (! Schema::hasTable('producto_impuestos')) {
            Schema::create('producto_impuestos', function (Blueprint $t) {
                $t->id();
                $t->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $t->foreignId('impuesto_id')->constrained('impuestos')->cascadeOnDelete();
                $t->timestamps();
                $t->unique(['producto_id', 'impuesto_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            foreach ([
                'tipo_siigo', 'stock_control', 'tax_classification', 'tax_included',
                'tax_consumption_value', 'modelo_siigo', 'barcode_padre', 'unit_label',
            ] as $col) {
                if (Schema::hasColumn('productos', $col)) $t->dropColumn($col);
            }
        });
        Schema::dropIfExists('producto_impuestos');
        if (Schema::hasColumn('impuestos', 'siigo_id')) {
            Schema::table('impuestos', fn (Blueprint $t) => $t->dropColumn('siigo_id'));
        }
        if (Schema::hasColumn('unidades_medida', 'codigo_unece')) {
            Schema::table('unidades_medida', fn (Blueprint $t) => $t->dropColumn('codigo_unece'));
        }
        if (Schema::hasColumn('listas_precios', 'siigo_id')) {
            Schema::table('listas_precios', fn (Blueprint $t) => $t->dropColumn('siigo_id'));
        }
        if (Schema::hasColumn('marcas', 'siigo_brand_name')) {
            Schema::table('marcas', fn (Blueprint $t) => $t->dropColumn('siigo_brand_name'));
        }
        if (Schema::hasTable('categorias') && Schema::hasColumn('categorias', 'siigo_account_group_id')) {
            Schema::table('categorias', fn (Blueprint $t) => $t->dropColumn('siigo_account_group_id'));
        }
    }
};
