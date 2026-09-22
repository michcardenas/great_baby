<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1.5 · Parche a `alertas_stock_disparadas` (hallazgo del auditor F1).
 * Sin CHECK · invariante en Model + FK + domain layer.
 * Índice único parcial se conserva porque no depende de CHECK.
 */
return new class extends Migration {

    public function up(): void
    {
        if (! Schema::hasColumn('alertas_stock_disparadas', 'producto_id')) {
            Schema::table('alertas_stock_disparadas', function (Blueprint $t) {
                $t->foreignId('producto_id')->nullable()->after('config_id')
                  ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
            });
        }

        DB::statement('
            UPDATE alertas_stock_disparadas d
              JOIN producto_variantes v ON d.variante_id = v.id
              SET d.producto_id = v.producto_id
              WHERE d.producto_id IS NULL AND d.variante_id IS NOT NULL
        ');

        $col = collect(DB::select("SHOW COLUMNS FROM alertas_stock_disparadas LIKE 'variante_id'"))->first();
        if ($col && strtoupper((string) $col->Null) === 'NO') {
            DB::statement('ALTER TABLE alertas_stock_disparadas MODIFY variante_id BIGINT UNSIGNED NULL');
        }

        try {
            DB::statement('ALTER TABLE alertas_stock_disparadas DROP CONSTRAINT IF EXISTS chk_alerta_disp_sujeto');
        } catch (\Throwable $e) {}

        // Índice único parcial para configs agregadas (previene duplicados).
        if (! Schema::hasColumn('alertas_stock_config', 'clave_agregada')) {
            try {
                DB::statement("
                    ALTER TABLE alertas_stock_config
                      ADD COLUMN clave_agregada VARCHAR(60) AS (
                        CASE
                          WHEN variante_id IS NULL AND producto_id IS NOT NULL
                            THEN CONCAT('p', producto_id, '-u', COALESCE(ubicacion_id, 0))
                          ELSE NULL
                        END
                      ) VIRTUAL
                ");
                DB::statement('CREATE UNIQUE INDEX uq_alerta_config_agregada ON alertas_stock_config (clave_agregada)');
            } catch (\Throwable $e) {
                // Si la versión de MariaDB no soporta columnas generadas, saltar el índice.
                // La lógica ya está en Model + Livewire form.
            }
        }
    }

    public function down(): void
    {
        try { DB::statement('DROP INDEX uq_alerta_config_agregada ON alertas_stock_config'); } catch (\Throwable $e) {}
        try { DB::statement('ALTER TABLE alertas_stock_config DROP COLUMN clave_agregada'); } catch (\Throwable $e) {}

        $huerfanos = DB::scalar('SELECT COUNT(*) FROM alertas_stock_disparadas WHERE variante_id IS NULL');
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado: {$huerfanos} filas de alertas_stock_disparadas tienen variante_id NULL."
            );
        }
        DB::statement('ALTER TABLE alertas_stock_disparadas MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        if (Schema::hasColumn('alertas_stock_disparadas', 'producto_id')) {
            Schema::table('alertas_stock_disparadas', function (Blueprint $t) {
                $t->dropConstrainedForeignId('producto_id');
            });
        }
    }
};
