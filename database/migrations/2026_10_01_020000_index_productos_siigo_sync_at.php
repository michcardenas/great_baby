<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A3 · Índice compuesto en productos(siigo_sync_at, siigo_id) y
 * (deleted_at, activo).
 *
 * Motivo · El comando siigo:limpiar-sandbox filtra por ventana de
 * siigo_sync_at (whereBetween). Sin índice la query hace table scan
 * sobre 23k+ productos. Con 100k productos (prod futura) sería
 * inaceptable.
 *
 * Mismo índice sirve al dashboard de /app/siigo (consultas "últimas
 * sincronizaciones") y a la papelera de sync (A2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if (! $this->hasIndex('productos', 'productos_siigo_sync_at_siigo_id_idx')) {
                $t->index(['siigo_sync_at', 'siigo_id'], 'productos_siigo_sync_at_siigo_id_idx');
            }
            if (! $this->hasIndex('productos', 'productos_deleted_activo_idx')) {
                $t->index(['deleted_at', 'activo'], 'productos_deleted_activo_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $t) {
            if ($this->hasIndex('productos', 'productos_siigo_sync_at_siigo_id_idx')) {
                $t->dropIndex('productos_siigo_sync_at_siigo_id_idx');
            }
            if ($this->hasIndex('productos', 'productos_deleted_activo_idx')) {
                $t->dropIndex('productos_deleted_activo_idx');
            }
        });
    }

    private function hasIndex(string $tabla, string $indice): bool
    {
        $rows = \DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = ?", [$indice]);
        return count($rows) > 0;
    }
};
