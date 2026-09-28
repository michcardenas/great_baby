<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F9 · siigo_id + siigo_sync_at en recepciones_compra.
 * La RECEPCIÓN (mercancía recibida) es el momento contable en que se
 * "causa" la factura de compra · por eso el sync SIIGO se ata acá y no
 * a la OC (que puede estar pendiente sin haber llegado).
 *
 * Idempotente (Schema::hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('compras_recepciones', function ($table) {
            if (! Schema::hasColumn('compras_recepciones', 'siigo_id')) {
                $table->string('siigo_id', 60)->nullable();
            }
            if (! Schema::hasColumn('compras_recepciones', 'siigo_number')) {
                $table->string('siigo_number', 60)->nullable();
            }
            if (! Schema::hasColumn('compras_recepciones', 'siigo_sync_at')) {
                $table->timestamp('siigo_sync_at')->nullable();
            }
        });

        Schema::table('compras_recepciones', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM compras_recepciones WHERE Key_name = 'compras_recepciones_siigo_id_uq'"
            ));
            if ($existente->isEmpty()) {
                $table->unique('siigo_id', 'compras_recepciones_siigo_id_uq');
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras_recepciones', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM compras_recepciones WHERE Key_name = 'compras_recepciones_siigo_id_uq'"
            ));
            if ($existente->isNotEmpty()) {
                $table->dropUnique('compras_recepciones_siigo_id_uq');
            }
            foreach (['siigo_sync_at', 'siigo_number', 'siigo_id'] as $col) {
                if (Schema::hasColumn('compras_recepciones', $col)) $table->dropColumn($col);
            }
        });
    }
};
