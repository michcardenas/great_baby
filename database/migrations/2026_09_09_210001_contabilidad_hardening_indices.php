<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-audit M5 DATOS-B1 · índices compuestos para agregaciones contables.
 *
 * Justificación:
 *   - `balanceComprobacion` filtra por rango de fecha y agrupa por cuenta_puc
 *     → sin índice compuesto (fecha, cuenta_puc) el motor hace filesort tras
 *     traer todo el rango. Con &gt;500k asientos el timeout es real.
 *   - `porOrigen` filtra por fecha y agrupa por origen_type.
 *   - Existen ya índices individuales en fecha, cuenta_puc, (origen_type, origen_id)
 *     de la migración inicial — los compuestos son ADICIONALES, no reemplazan.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('movimientos_contables')) return;

        // Re-audit R2 DATOS-M1 · SHOW INDEX es MariaDB/MySQL. En SQLite (tests
        // con RefreshDatabase) crashea. Guard por driver + fallback vacío.
        $driver = DB::connection()->getDriverName();
        $existentes = [];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $existentes = collect(DB::select("SHOW INDEX FROM movimientos_contables"))
                ->pluck('Key_name')->unique()->all();
        }

        Schema::table('movimientos_contables', function (\Illuminate\Database\Schema\Blueprint $t) use ($existentes) {
            if (! in_array('movs_fecha_cuenta_idx', $existentes, true)) {
                $t->index(['fecha', 'cuenta_puc'], 'movs_fecha_cuenta_idx');
            }
            if (! in_array('movs_fecha_origen_idx', $existentes, true)) {
                $t->index(['fecha', 'origen_type'], 'movs_fecha_origen_idx');
            }
        });
    }

    public function down(): void
    {
        // Down individual, cada dropIndex en su propio Schema::table para que
        // un índice ausente no aborte los otros (los try dentro del closure
        // no atrapan porque el fallo viene del execute() del schema builder).
        if (! Schema::hasTable('movimientos_contables')) return;
        foreach (['movs_fecha_cuenta_idx', 'movs_fecha_origen_idx'] as $idx) {
            try {
                Schema::table('movimientos_contables', fn (\Illuminate\Database\Schema\Blueprint $t) => $t->dropIndex($idx));
            } catch (\Throwable) {}
        }
    }
};
