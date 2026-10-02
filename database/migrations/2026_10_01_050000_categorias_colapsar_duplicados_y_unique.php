<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FASE F4 · consolidar categorías duplicadas por diferencia de mayúsculas
 * ("Vestidos" ≠ "vestidos" en MySQL con selección case-sensitive, pero sí
 * iguales con utf8mb4_unicode_ci; el import ya hacía LOWER() en el cache,
 * pero el panel admin podía crear ambas versiones).
 *
 * Flujo seguro:
 *   1. Detectar grupos por LOWER(nombre).
 *   2. Elegir canónica = la de id más bajo (la más antigua).
 *   3. Reasignar productos.categoria_id de las duplicadas → canónica.
 *   4. Eliminar las duplicadas vacías.
 *   5. Agregar UNIQUE INDEX en nombre para prevenir recurrencia.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1-4 · consolidación.
        $grupos = DB::table('categorias')
            ->selectRaw('LOWER(nombre) as low, MIN(id) as canonico_id, COUNT(*) as n')
            ->groupBy(DB::raw('LOWER(nombre)'))
            ->having('n', '>', 1)
            ->get();

        foreach ($grupos as $g) {
            $duplicadas = DB::table('categorias')
                ->whereRaw('LOWER(nombre) = ?', [$g->low])
                ->where('id', '!=', $g->canonico_id)
                ->pluck('id');

            if ($duplicadas->isNotEmpty()) {
                DB::table('productos')
                    ->whereIn('categoria_id', $duplicadas)
                    ->update(['categoria_id' => $g->canonico_id]);
                DB::table('categorias')->whereIn('id', $duplicadas)->delete();
            }
        }

        // 5 · UNIQUE sobre nombre · bajo utf8mb4_unicode_ci la comparación
        // ya es case-insensitive, así que esto bloquea el caso real.
        Schema::table('categorias', function ($t) {
            $t->unique('nombre', 'categorias_nombre_unique');
        });
    }

    public function down(): void
    {
        Schema::table('categorias', function ($t) {
            $t->dropUnique('categorias_nombre_unique');
        });
    }
};
