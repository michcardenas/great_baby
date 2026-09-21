<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1 · Desglose dual de stock — Migración 1/6
 *
 *   `desglose_stock` = true  → producto lleva stock por VARIANTE (comportamiento clásico).
 *                     false → producto lleva stock AGREGADO en `stock_directo` (nuevo).
 *
 *   Todo producto existente arranca con true → cero impacto retroactivo.
 *   El toggle solo se puede cambiar si el producto NO tiene movimientos en
 *   `inventario_movimientos` (política enforced en Fase 1 migración 7).
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->boolean('desglose_stock')->default(true)->after('activo')
              ->comment('true=stock por variante, false=stock agregado en stock_directo');
            $t->decimal('stock_directo', 14, 4)->default(0)->after('desglose_stock')
              ->comment('Stock agregado usado SOLO cuando desglose_stock=false');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            $t->dropColumn(['desglose_stock', 'stock_directo']);
        });
    }
};
