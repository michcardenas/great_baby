<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-audit R3 M4 Cartera · ronda 3.
 *
 *   R3-06 backfill · uppercase + trim de `facturas_consecutivos.prefijo`.
 *     SiguienteConsecutivoFactura ya normaliza en input; esta migración
 *     migra datos legacy y detecta colisiones (dos filas que solo diferían
 *     en case). Ante colisión, mueve el contador máximo al UPPER canónico
 *     y elimina la variante.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('facturas_consecutivos')) return;

        $rows = DB::table('facturas_consecutivos')->get();
        $canon = [];
        foreach ($rows as $r) {
            $up = strtoupper(trim($r->prefijo));
            $canon[$up] ??= ['ids' => [], 'max' => 0];
            $canon[$up]['ids'][] = $r->id;
            $canon[$up]['max'] = max($canon[$up]['max'], (int) $r->siguiente);
        }

        foreach ($canon as $up => $data) {
            $keepId = $data['ids'][0];
            DB::table('facturas_consecutivos')->where('id', $keepId)->update([
                'prefijo' => $up,
                'siguiente' => $data['max'],
                'updated_at' => now(),
            ]);
            if (count($data['ids']) > 1) {
                DB::table('facturas_consecutivos')
                    ->whereIn('id', array_slice($data['ids'], 1))
                    ->delete();
            }
        }

        // Backfill `notas_credito.prefijo` legacy también.
        if (Schema::hasTable('notas_credito')) {
            DB::statement('UPDATE notas_credito SET prefijo = UPPER(TRIM(prefijo)) WHERE prefijo <> UPPER(TRIM(prefijo))');
        }
    }

    public function down(): void
    {
        // Irreversible: no se puede reconstruir el case original.
    }
};
