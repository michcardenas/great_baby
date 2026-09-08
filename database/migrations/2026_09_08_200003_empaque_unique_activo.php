<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MariaDB no soporta índices únicos parciales (WHERE), pero sí columnas generadas.
 * Truco: columna `pedido_activo` = pedido_id si estado='en_curso', NULL en otro caso.
 * NULL no cuenta en UNIQUE → efectivamente único por pedido mientras esté en_curso.
 * Es el blindaje final contra el race del hallazgo #5.
 */
return new class extends Migration {
    public function up(): void
    {
        // Antes de agregar el índice, limpiar duplicados en_curso si existen (idempotencia).
        DB::statement("
            UPDATE empaques_registro
            SET estado = 'anulado', fin_at = NOW()
            WHERE estado = 'en_curso'
              AND id NOT IN (
                SELECT id FROM (
                    SELECT MAX(id) as id FROM empaques_registro WHERE estado = 'en_curso' GROUP BY pedido_id
                ) t
              )
        ");

        Schema::table('empaques_registro', function (Blueprint $t) {
            // Columna generada virtual: solo se llena cuando estado='en_curso'
            $t->unsignedBigInteger('pedido_activo')
                ->virtualAs("CASE WHEN estado = 'en_curso' THEN pedido_id ELSE NULL END")
                ->nullable();
            $t->unique('pedido_activo', 'uniq_empaque_activo_por_pedido');
        });
    }

    public function down(): void
    {
        Schema::table('empaques_registro', function (Blueprint $t) {
            $t->dropUnique('uniq_empaque_activo_por_pedido');
            $t->dropColumn('pedido_activo');
        });
    }
};
