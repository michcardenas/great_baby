<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill: registros con foto_path pero foto_at NULL (creados antes de la migración
 * de foto_at) no eran purgables → Habeas Data no se cumplía en el histórico.
 * Fix S-12 auditor Seguridad.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            UPDATE empaques_registro
            SET foto_at = COALESCE(fin_at, inicio_at, created_at)
            WHERE foto_path IS NOT NULL AND foto_at IS NULL
        ");
    }

    public function down(): void
    {
        // No hay downgrade — el timestamp es un backfill defensivo.
    }
};
