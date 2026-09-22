<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C-F1.5 · Desglose dual — parche a `alertas_stock_disparadas` (hallazgo del auditor F1).
 *
 * VerificarAlertasStock crea filas en `alertas_stock_disparadas` con
 * `variante_id = $config->variante_id`. Sin este parche, cuando el config es
 * agregado (`producto_id NOT NULL, variante_id NULL`), el INSERT explota porque
 * la columna es NOT NULL.
 *
 * Adicional: unique parcial en `alertas_stock_config` para prevenir duplicados
 * de config agregada del mismo `(producto_id, ubicacion_id)`.
 */
return new class extends Migration {

    public function up(): void
    {
        // 1) alertas_stock_disparadas: producto_id + variante_id nullable + CHECK.
        Schema::table('alertas_stock_disparadas', function (Blueprint $t) {
            $t->foreignId('producto_id')->nullable()->after('config_id')
              ->constrained('productos')->cascadeOnUpdate()->restrictOnDelete();
        });

        DB::statement('
            UPDATE alertas_stock_disparadas d
              JOIN producto_variantes v ON d.variante_id = v.id
              SET d.producto_id = v.producto_id
              WHERE d.producto_id IS NULL
        ');

        DB::statement('ALTER TABLE alertas_stock_disparadas MODIFY variante_id BIGINT UNSIGNED NULL');
        DB::statement('
            ALTER TABLE alertas_stock_disparadas
              ADD CONSTRAINT chk_alerta_disp_sujeto
              CHECK (variante_id IS NOT NULL OR producto_id IS NOT NULL)
        ');

        // 2) alertas_stock_config: unique parcial para configs agregadas.
        //    Previene 2+ configs sobre el mismo (producto_id, ubicacion_id) con variante_id=NULL.
        //    Usamos columna generada porque MariaDB 10.4 no soporta índice parcial WHERE.
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
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_alerta_config_agregada ON alertas_stock_config');
        DB::statement('ALTER TABLE alertas_stock_config DROP COLUMN IF EXISTS clave_agregada');

        DB::statement('ALTER TABLE alertas_stock_disparadas DROP CONSTRAINT IF EXISTS chk_alerta_disp_sujeto');
        // Guard: si hay filas con variante_id NULL, no podemos volver a NOT NULL.
        $huerfanos = DB::scalar('SELECT COUNT(*) FROM alertas_stock_disparadas WHERE variante_id IS NULL');
        if ($huerfanos > 0) {
            throw new \RuntimeException(
                "Rollback bloqueado: {$huerfanos} filas de alertas_stock_disparadas tienen variante_id NULL. ".
                "Bórralas o migralas antes de rollback."
            );
        }
        DB::statement('ALTER TABLE alertas_stock_disparadas MODIFY variante_id BIGINT UNSIGNED NOT NULL');
        Schema::table('alertas_stock_disparadas', function (Blueprint $t) {
            $t->dropConstrainedForeignId('producto_id');
        });
    }
};
