<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una ubicación pertenece a una bodega, y dice dónde está parada la mercancía.
 *
 * Hasta ahora `inventario_ubicaciones` era una lista PLANA: «Bodega Principal
 * Bogotá» y «Rack A · Nivel 01» eran lo mismo para la base, sin nada que dijera
 * que el rack está dentro de esa bodega. Eso rompía dos cosas:
 *
 *   · Los permisos. Al responsable de una bodega se le asigna la bodega, pero
 *     los conteos y traslados se hacen sobre los racks. Medido: Don Jorge
 *     podía operar 1 de 64 ubicaciones y todo lo real le daba 403.
 *   · Encontrar la mercancía. No había forma de decir «los pañales de recién
 *     nacido están en el pasillo 4, estante 6, nivel 3».
 *
 * `bodega_id` apunta a la ubicación padre (la bodega). Una bodega es la que no
 * tiene padre. Pasillo, estante y nivel son opcionales: sirven para ubicar
 * físicamente y para armar el código legible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $t) {
            if (! Schema::hasColumn('inventario_ubicaciones', 'bodega_id')) {
                $t->foreignId('bodega_id')->nullable()->after('id')
                    ->constrained('inventario_ubicaciones')->nullOnDelete();
            }
            foreach (['pasillo' => 30, 'estante' => 30, 'nivel' => 30] as $col => $largo) {
                if (! Schema::hasColumn('inventario_ubicaciones', $col)) {
                    $t->string($col, $largo)->nullable()->after('nombre');
                }
            }
        });

        // Las que ya tienen responsable son bodegas de verdad (sedes): se dejan
        // como raíz. El resto queda sin bodega a propósito — qué rack pertenece
        // a qué sede lo sabe quien maneja la bodega, no se adivina, y colgarlos
        // de la bodega equivocada daría permisos a quien no corresponde.
        DB::table('inventario_ubicaciones')
            ->whereNotNull('responsable_user_id')
            ->update(['bodega_id' => null]);
    }

    public function down(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $t) {
            if (Schema::hasColumn('inventario_ubicaciones', 'bodega_id')) {
                $t->dropConstrainedForeignId('bodega_id');
            }
            foreach (['pasillo', 'estante', 'nivel'] as $col) {
                if (Schema::hasColumn('inventario_ubicaciones', $col)) {
                    $t->dropColumn($col);
                }
            }
        });
    }
};
