<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Agrega UNIQUE (variante_id, lista_id) a `precios_variante`.
 *
 * Sin este índice, el `updateOrInsert` del importador Excel SIIGO siempre
 * cae en INSERT (porque MySQL necesita un índice único para detectar la
 * fila existente) · lo que generaba filas duplicadas al re-importar.
 *
 * Antes de crear el unique limpiamos duplicados dejando la fila más
 * reciente de cada par (variante_id, lista_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1 · Purga duplicados (ya no sirven, se re-generan al re-importar).
        DB::statement("
            DELETE p1 FROM precios_variante p1
            INNER JOIN precios_variante p2
                ON p1.variante_id = p2.variante_id
               AND p1.lista_id = p2.lista_id
               AND p1.id < p2.id
        ");

        // 2 · Añade el unique. El índice compuesto previo (variante_id,
        //     lista_id, vigente_desde) permanece como índice NO-único.
        Schema::table('precios_variante', function (Blueprint $table) {
            $table->unique(['variante_id', 'lista_id'], 'precios_variante_variante_lista_unique');
        });
    }

    public function down(): void
    {
        Schema::table('precios_variante', function (Blueprint $table) {
            $table->dropUnique('precios_variante_variante_lista_unique');
        });
    }
};
