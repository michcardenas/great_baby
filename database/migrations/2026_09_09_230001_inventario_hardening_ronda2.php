<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * M3 INV · Re-audit ronda 2 · endurecimiento de schema.
 *
 * PATRÓN ι · kardex append-only: agrega updated_at, deleted_at (SoftDeletes)
 *   y expone costo_unit decimal para PMP real (habilita μ). Cambia `cantidad`
 *   a decimal(14,4) para permitir kits/telas fraccionarios (DATOS-C1).
 *
 * PATRÓN ξ · cantidades decimales en traslados y tomas (DATOS-C2), CHECK
 *   cantidad > 0 en reservas (DATOS-C3), UNIQUE parcial de alerta abierta
 *   vía columna generated stored (DATOS-C7), agrega índice inverso kardex.
 *
 * PATRÓN o · trigger DB append-only en inventario_movimientos: bloquea UPDATE
 *   de columnas contables (variante_id, ubicacion_id, cantidad, tipo). Defensa
 *   en profundidad frente al guard Eloquent que se pierde con query directa.
 *
 * PATRÓN κ · trigger DB que bloquea UPDATE del `estado` de un traslado
 *   fuera de la state machine válida (defensa frente a Eloquent bulk update
 *   que salta booted::saving).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ============ PATRÓN ι · Kardex append-only + PMP ============
        Schema::table('inventario_movimientos', function (Blueprint $table) {
            // Comentario: `cantidad` YA existía como integer; migramos a
            // decimal(14,4) para admitir fraccionarios (telas/kits) sin
            // perder precisión histórica.
            $table->decimal('cantidad', 14, 4)->change();

            // PMP real (patrón μ): cada movimiento guarda su costo unitario
            // asociado, para que PrepararTomaFisica calcule el PMP verdadero
            // en vez de caer al precio_proveedor del maestro.
            $table->decimal('costo_unit', 14, 4)->nullable()
                ->after('cantidad')->comment('Costo unitario del movimiento (PMP)');

            // updated_at para trazar cualquier corrección administrativa vía
            // Filament escape hatch (aunque booted::updating las bloquee, si
            // Gerencia usa forceUpdate en Tinker queda registro).
            $table->timestamp('updated_at')->nullable()->after('created_at');

            $table->softDeletes()->after('updated_at');

            // Índice inverso para reportes por bodega.
            $table->index(['ubicacion_id', 'variante_id', 'created_at'], 'idx_kardex_inv');
        });

        // TRIGGER MySQL/MariaDB: kardex append-only a nivel motor.
        // Bloquea UPDATE de columnas contables. Sólo `notas`, `updated_at`,
        // `deleted_at` pueden mutarse (borrado lógico + anotaciones admin).
        DB::unprepared("DROP TRIGGER IF EXISTS trg_kardex_append_only");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_kardex_append_only
            BEFORE UPDATE ON inventario_movimientos
            FOR EACH ROW
            BEGIN
                IF NEW.variante_id <> OLD.variante_id
                   OR NEW.ubicacion_id <> OLD.ubicacion_id
                   OR NEW.cantidad <> OLD.cantidad
                   OR NEW.tipo <> OLD.tipo
                   OR NEW.referencia_tipo <=> OLD.referencia_tipo = 0
                   OR NEW.referencia_id <=> OLD.referencia_id = 0
                THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Kardex append-only: solo notas/updated_at/deleted_at editables';
                END IF;
            END
        SQL);

        // ============ PATRÓN ξ · Cantidades decimales ============
        Schema::table('traslados_inventario_items', function (Blueprint $table) {
            $table->decimal('cantidad_solicitada', 14, 4)->change();
            $table->decimal('cantidad_ejecutada', 14, 4)->default(0)->change();
        });

        Schema::table('tomas_fisicas_items', function (Blueprint $table) {
            $table->decimal('saldo_sistema', 14, 4)->change();
            $table->decimal('cantidad_contada', 14, 4)->nullable()->change();
            $table->decimal('diferencia', 14, 4)->default(0)->change();
        });

        // ============ PATRÓN ξ · CHECK cantidad>0 en reservas ============
        // MariaDB 10.2+ soporta CHECK constraint.
        try {
            DB::statement("ALTER TABLE reservas_inventario ADD CONSTRAINT ck_reserva_positiva CHECK (cantidad > 0)");
        } catch (\Throwable $e) {
            // Si ya existe o el motor no lo soporta, seguir. El guard Eloquent
            // en ReservarStock ya valida cantidad>0 desde ronda 1.
        }

        // ============ PATRÓN ξ · UNIQUE parcial alerta abierta ============
        // El UNIQUE actual (config_id, tipo, resuelta) es todo-o-nada:
        // permite duplicados cuando resuelta=1 (dos alertas mismas resueltas
        // el mismo día) — no bloquea. Necesitamos: UNIQUE de la alerta
        // ABIERTA (resuelta=0). MariaDB no soporta UNIQUE parcial nativo,
        // pero se emula con columna generada stored que es NULL si resuelta=1.
        Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
            $table->string('clave_abierta', 60)->virtualAs(
                "CASE WHEN resuelta = 0 THEN CONCAT(config_id, '-', tipo) ELSE NULL END"
            )->nullable();
        });
        // Reemplaza el índice viejo por uno sobre la columna virtual.
        try {
            Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
                $table->dropUnique('idx_alerta_unica_abierta');
            });
        } catch (\Throwable $e) {}
        Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
            $table->unique('clave_abierta', 'idx_alerta_abierta_unica');
        });

        // ============ PATRÓN κ · Trigger state machine traslados ============
        DB::unprepared("DROP TRIGGER IF EXISTS trg_traslado_estado_valido");
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_traslado_estado_valido
            BEFORE UPDATE ON traslados_inventario
            FOR EACH ROW
            BEGIN
                IF NEW.estado <> OLD.estado THEN
                    IF NOT (
                        (OLD.estado = 'borrador'    AND NEW.estado IN ('en_transito','anulado')) OR
                        (OLD.estado = 'en_transito' AND NEW.estado IN ('recibido','anulado')) OR
                        (OLD.estado = 'recibido'    AND NEW.estado = 'anulado')
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Transicion de estado de traslado no permitida';
                    END IF;
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared("DROP TRIGGER IF EXISTS trg_kardex_append_only");
        DB::unprepared(<<<'SQL'
            DROP TRIGGER IF EXISTS trg_traslado_estado_valido
        SQL);

        Schema::table('alertas_stock_disparadas', function (Blueprint $table) {
            try { $table->dropUnique('idx_alerta_abierta_unica'); } catch (\Throwable $e) {}
            try { $table->dropColumn('clave_abierta'); } catch (\Throwable $e) {}
            try { $table->unique(['config_id', 'tipo', 'resuelta'], 'idx_alerta_unica_abierta'); } catch (\Throwable $e) {}
        });

        try {
            DB::statement("ALTER TABLE reservas_inventario DROP CONSTRAINT ck_reserva_positiva");
        } catch (\Throwable $e) {}

        Schema::table('inventario_movimientos', function (Blueprint $table) {
            try { $table->dropIndex('idx_kardex_inv'); } catch (\Throwable $e) {}
            try { $table->dropSoftDeletes(); } catch (\Throwable $e) {}
            try { $table->dropColumn(['updated_at', 'costo_unit']); } catch (\Throwable $e) {}
            $table->integer('cantidad')->change();
        });
    }
};
