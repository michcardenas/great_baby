<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B3-M5 · índice compuesto (recurso, estado, created_at) en siigo_sync_log
 *
 * Los widgets/reportes de Filament filtran por (recurso='productos' AND
 * estado='fallido' ORDER BY created_at DESC). Sin índice compuesto el listado
 * degrada rápido: con ~48k rows/día se llega a millones en meses.
 *
 * La purga programada la hace el schedule (comando `siigo:purgar-logs`).
 * Ver `routes/console.php`.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('siigo_sync_log')) return;

        Schema::table('siigo_sync_log', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM siigo_sync_log WHERE Key_name = 'siigo_sync_log_recurso_estado_created_idx'"
            ));
            if ($existente->isEmpty()) {
                $table->index(
                    ['recurso', 'estado', 'created_at'],
                    'siigo_sync_log_recurso_estado_created_idx',
                );
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('siigo_sync_log')) return;

        Schema::table('siigo_sync_log', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM siigo_sync_log WHERE Key_name = 'siigo_sync_log_recurso_estado_created_idx'"
            ));
            if ($existente->isNotEmpty()) {
                $table->dropIndex('siigo_sync_log_recurso_estado_created_idx');
            }
        });
    }
};
