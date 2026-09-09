<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-audit M5 R2 DATOS-A2 · índice inverso (cuenta_puc, fecha).
 *
 * Justificación: el filtro multi-cuenta del reporte de retenciones DIAN
 * (`?cuentas=2365,2367,2368`) hace `WHERE cuenta_puc LIKE 'X%' AND fecha BETWEEN`.
 * Con el índice existente `(fecha, cuenta_puc)` + range en fecha, MariaDB abandona
 * la segunda columna. Este índice equality-then-range es dramáticamente mejor
 * cuando el filtro conoce la cuenta.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('movimientos_contables')) return;

        $driver = DB::connection()->getDriverName();
        $existentes = [];
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $existentes = collect(DB::select("SHOW INDEX FROM movimientos_contables"))
                ->pluck('Key_name')->unique()->all();
        }

        if (! in_array('movs_cuenta_fecha_idx', $existentes, true)) {
            Schema::table('movimientos_contables', fn (\Illuminate\Database\Schema\Blueprint $t) => $t->index(['cuenta_puc', 'fecha'], 'movs_cuenta_fecha_idx'));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('movimientos_contables')) return;
        try {
            Schema::table('movimientos_contables', fn (\Illuminate\Database\Schema\Blueprint $t) => $t->dropIndex('movs_cuenta_fecha_idx'));
        } catch (\Throwable) {}
    }
};
