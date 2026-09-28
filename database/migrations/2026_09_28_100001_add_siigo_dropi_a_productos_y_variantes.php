<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 1 (Siigo sync) · agrega columnas de mapeo cross-sistema:
 *
 *   productos.dropi_sku                → SKU raíz de Dropi (sin variación).
 *                                        Se llena cuando se vincula el 1er
 *                                        producto pendiente de Dropi al ERP.
 *
 *   producto_variantes.dropi_variacion_id → ID único de variación Dropi
 *                                            (Azul-M = un id). Es lo que
 *                                            identifica un ítem en pedidos.
 *
 *   producto_variantes.siigo_id       → UUID del producto en Siigo Nube.
 *   producto_variantes.siigo_code     → Código único en Siigo (mismo `code`
 *                                        que enviamos en el POST /v1/products).
 *   producto_variantes.siigo_sync_at  → Última vez que se sincronizó a Siigo
 *                                        (para saber si el push está al día).
 *
 * Estrategia · variantes se mapean 1-a-1 con productos en Siigo cuando el
 * producto padre tiene `desglose_stock=true`. Cada variante es un `code`
 * único en Siigo (Siigo no entiende variantes por talla/color).
 * Cuando `desglose_stock=false`, solo el producto padre va a Siigo (usando
 * `productos.siigo_id/code` que ya existen desde migración anterior).
 *
 * Todo aditivo (nullable), sin romper datos existentes.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! Schema::hasColumn('productos', 'dropi_sku')) {
                $t->string('dropi_sku', 100)->nullable()->after('referencia')
                  ->comment('SKU raíz de Dropi. UNIQUE porque un SKU Dropi mapea 1-a-1 a un producto ERP.');
                // A1 · unique (no solo index): la vinculación es 1-a-1.
                $t->unique('dropi_sku', 'productos_dropi_sku_uq');
            }
        });

        Schema::table('producto_variantes', function (Blueprint $t) {
            if (! Schema::hasColumn('producto_variantes', 'dropi_variacion_id')) {
                $t->string('dropi_variacion_id', 100)->nullable()->after('codigo_barras')
                  ->comment('ID único de variación Dropi. UNIQUE porque cada variación Dropi corresponde a UNA variante ERP.');
                // A2 · unique (no solo index): cada variación Dropi es única.
                $t->unique('dropi_variacion_id', 'pv_dropi_variacion_uq');
            }
            if (! Schema::hasColumn('producto_variantes', 'siigo_id')) {
                $t->string('siigo_id', 60)->nullable()->after('dropi_variacion_id')
                  ->unique('pv_siigo_id_uq')
                  ->comment('UUID del producto en Siigo (cuando desglose_stock=true).');
            }
            if (! Schema::hasColumn('producto_variantes', 'siigo_code')) {
                $t->string('siigo_code', 60)->nullable()->after('siigo_id')
                  ->comment('Code enviado a Siigo (típicamente = codigo_barras). UNIQUE: Siigo rechaza codes duplicados.');
                // M2 · unique: Siigo API rechaza codes duplicados con 400.
                $t->unique('siigo_code', 'pv_siigo_code_uq');
            }
            if (! Schema::hasColumn('producto_variantes', 'siigo_sync_at')) {
                $t->timestamp('siigo_sync_at')->nullable()->after('siigo_code')
                  ->comment('Última sincronización a Siigo.');
            }
        });
    }

    public function down(): void
    {
        Schema::table('producto_variantes', function (Blueprint $t) {
            if (Schema::hasColumn('producto_variantes', 'siigo_sync_at')) $t->dropColumn('siigo_sync_at');
            if (Schema::hasColumn('producto_variantes', 'siigo_code')) {
                $t->dropUnique('pv_siigo_code_uq');
                $t->dropColumn('siigo_code');
            }
            if (Schema::hasColumn('producto_variantes', 'siigo_id')) {
                $t->dropUnique('pv_siigo_id_uq');
                $t->dropColumn('siigo_id');
            }
            if (Schema::hasColumn('producto_variantes', 'dropi_variacion_id')) {
                $t->dropUnique('pv_dropi_variacion_uq');
                $t->dropColumn('dropi_variacion_id');
            }
        });
        Schema::table('productos', function (Blueprint $t) {
            if (Schema::hasColumn('productos', 'dropi_sku')) {
                $t->dropUnique('productos_dropi_sku_uq');
                $t->dropColumn('dropi_sku');
            }
        });
    }
};
