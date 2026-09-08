<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REU-1: parametrización contable por producto.
 * Aracely: "en el sistema viejo cuando uno crea el producto, esa es la parametrización contable,
 *          esa es la que genera el balance".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->string('cta_ingreso', 30)->nullable()->after('impuesto_id');
            $t->string('cta_iva_venta', 30)->nullable()->after('cta_ingreso');
            $t->string('cta_costo', 30)->nullable()->after('cta_iva_venta');
            $t->string('cta_inventario', 30)->nullable()->after('cta_costo');
            $t->string('cta_devolucion', 30)->nullable()->after('cta_inventario');
            $t->string('cta_descuento', 30)->nullable()->after('cta_devolucion');
            $t->string('centro_costo', 20)->nullable()->after('cta_descuento');
            $t->text('notas_contables')->nullable()->after('centro_costo');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->dropColumn(['cta_ingreso', 'cta_iva_venta', 'cta_costo', 'cta_inventario',
                'cta_devolucion', 'cta_descuento', 'centro_costo', 'notas_contables']);
        });
    }
};
