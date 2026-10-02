<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FASE H1 · Paridad del form de productos con el "Creación de producto" oficial
 * de SIIGO (los 3 screenshots que mandó Aracely).
 *
 * Agrega 5 columnas que no existían en productos:
 *   - visible_en_facturas · toggle "Visible en facturas de venta" del header
 *   - retencion_siigo_id  · FK a impuestos tipo retención (tab Impuestos)
 *   - impuesto_cargo_dos_id · FK a impuesto cargo secundario (tab Impuestos)
 *   - reference_fabrica   · "Referencia de fábrica" (distinto del SKU/code)
 *   - stock_minimo        · "Stock mínimo" (tab Descripción y stock)
 *
 * Y crea producto_imagenes con 5 slots para el tab "Subir imágenes".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'visible_en_facturas')) {
                $t->boolean('visible_en_facturas')->default(true)->after('stock_control');
            }
            if (! Schema::hasColumn('productos', 'retencion_siigo_id')) {
                $t->unsignedBigInteger('retencion_siigo_id')->nullable()->after('impuesto_id');
                $t->index('retencion_siigo_id', 'productos_retencion_idx');
            }
            if (! Schema::hasColumn('productos', 'impuesto_cargo_dos_id')) {
                $t->unsignedBigInteger('impuesto_cargo_dos_id')->nullable()->after('retencion_siigo_id');
                $t->index('impuesto_cargo_dos_id', 'productos_impuesto_dos_idx');
            }
            if (! Schema::hasColumn('productos', 'reference_fabrica')) {
                $t->string('reference_fabrica', 60)->nullable()->after('referencia');
            }
            if (! Schema::hasColumn('productos', 'stock_minimo')) {
                $t->decimal('stock_minimo', 14, 4)->nullable()->after('stock_directo');
            }
        });

        if (! Schema::hasTable('producto_imagenes')) {
            Schema::create('producto_imagenes', function (Blueprint $t) {
                $t->id();
                $t->foreignId('producto_id')
                    ->constrained('productos')
                    ->cascadeOnDelete();
                $t->string('path', 255);                // storage path relativo
                $t->string('nombre_original', 255)->nullable();
                $t->unsignedInteger('tamano_bytes')->default(0);
                $t->string('mime', 50)->nullable();
                $t->unsignedTinyInteger('orden')->default(0);  // 0..4
                $t->timestamps();

                $t->index(['producto_id', 'orden'], 'producto_imagenes_prod_orden_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_imagenes');

        Schema::table('productos', function (Blueprint $t) {
            foreach (['visible_en_facturas', 'retencion_siigo_id', 'impuesto_cargo_dos_id', 'reference_fabrica', 'stock_minimo'] as $col) {
                if (Schema::hasColumn('productos', $col)) {
                    try { $t->dropIndex('productos_retencion_idx'); } catch (\Throwable) {}
                    try { $t->dropIndex('productos_impuesto_dos_idx'); } catch (\Throwable) {}
                    $t->dropColumn($col);
                }
            }
        });
    }
};
