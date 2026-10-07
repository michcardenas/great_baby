<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LOG-J8 · Clasificación por línea de recepción.
 *
 * Don Jorge: hoy TODA la mercancía entra como apta aunque llegue averiada o
 * dudosa; eso corrompe el stock "Apto venta" y es la raíz del descuadre
 * contable. La propuesta es que al registrar la recepción, cada línea
 * declare su destino (apto / averia / cuarentena / revision / faltante) y
 * un motivo. El stock se asigna al almacén que corresponde de una.
 *
 * Default = 'apto' por compatibilidad con el flujo actual (que es como
 * ingresa hoy el 100% del inventario). El form admin pide elegir
 * explícitamente para cada línea pero no rompe recepciones ya guardadas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_recepcion_items', function (Blueprint $t) {
            $t->string('clasificacion', 15)->default('apto')->after('subtotal');
            $t->string('motivo_clasificacion', 180)->nullable()->after('clasificacion');
            $t->index('clasificacion');
        });
    }

    public function down(): void
    {
        Schema::table('compras_recepcion_items', function (Blueprint $t) {
            $t->dropIndex(['clasificacion']);
            $t->dropColumn(['clasificacion', 'motivo_clasificacion']);
        });
    }
};
