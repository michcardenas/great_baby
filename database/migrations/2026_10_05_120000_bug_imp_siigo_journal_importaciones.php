<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BUG-IMP · Columnas de idempotencia para que la liquidación de importación
 * pueda tener su journal SIIGO sin generar duplicados en reintentos.
 *
 * - importaciones.siigo_journal_id · id del journal creado por PushLiquidacionImportacionASiigo.
 * - importaciones.siigo_sync_at · timestamp de la última foto enviada.
 * - movimientos_contables.siigo_journal_id · si llegó incluido en un journal
 *   compuesto, se marca aquí para no volver a mandarlo individual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compras_importaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('compras_importaciones', 'siigo_journal_id')) {
                $table->string('siigo_journal_id', 64)->nullable()->after('fecha_liquidacion')->index();
            }
            if (! Schema::hasColumn('compras_importaciones', 'siigo_sync_at')) {
                $table->timestamp('siigo_sync_at')->nullable()->after('siigo_journal_id');
            }
        });

        Schema::table('movimientos_contables', function (Blueprint $table) {
            if (! Schema::hasColumn('movimientos_contables', 'siigo_journal_id')) {
                $table->string('siigo_journal_id', 64)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('compras_importaciones', function (Blueprint $table) {
            if (Schema::hasColumn('compras_importaciones', 'siigo_sync_at')) $table->dropColumn('siigo_sync_at');
            if (Schema::hasColumn('compras_importaciones', 'siigo_journal_id')) {
                $table->dropIndex(['siigo_journal_id']);
                $table->dropColumn('siigo_journal_id');
            }
        });
        Schema::table('movimientos_contables', function (Blueprint $table) {
            if (Schema::hasColumn('movimientos_contables', 'siigo_journal_id')) {
                $table->dropIndex(['siigo_journal_id']);
                $table->dropColumn('siigo_journal_id');
            }
        });
    }
};
