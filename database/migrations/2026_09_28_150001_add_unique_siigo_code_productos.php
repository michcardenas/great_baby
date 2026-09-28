<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B2-A6 · UNIQUE en productos.siigo_code
 *
 * `siigo_id` ya era UNIQUE (idx `productos_siigo_id_unique`) pero `siigo_code`
 * no. Sin este UNIQUE, la reconciliación `already_exists` podría linkear dos
 * filas locales al mismo producto SIIGO si dos productos comparten referencia
 * reciclada (o si se corre el mismo push dos veces por error).
 *
 * Idempotente vía verificación de índice previo. Antes del ADD limpiamos
 * duplicados de siigo_code (dejamos el registro más antiguo, seteamos NULL
 * al resto) para no fallar el ALTER.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1) Purga defensiva de duplicados de siigo_code (dejar el min(id)).
        $dups = DB::table('productos')
            ->select('siigo_code', DB::raw('MIN(id) as keep_id'), DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('siigo_code')
            ->groupBy('siigo_code')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($dups as $d) {
            DB::table('productos')
                ->where('siigo_code', $d->siigo_code)
                ->where('id', '!=', $d->keep_id)
                ->update(['siigo_code' => null]);
        }

        // 2) UNIQUE (idempotente).
        Schema::table('productos', function ($table) {
            $existing = collect(DB::select("SHOW INDEXES FROM productos WHERE Key_name = 'productos_siigo_code_uq'"));
            if ($existing->isEmpty()) {
                $table->unique('siigo_code', 'productos_siigo_code_uq');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function ($table) {
            $existing = collect(DB::select("SHOW INDEXES FROM productos WHERE Key_name = 'productos_siigo_code_uq'"));
            if ($existing->isNotEmpty()) {
                $table->dropUnique('productos_siigo_code_uq');
            }
        });
    }
};
