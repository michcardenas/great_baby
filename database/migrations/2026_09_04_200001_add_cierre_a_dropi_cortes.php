<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cierre formal de corte con manifiesto firmado.
 * - manifiesto_hash: SHA-256 del snapshot del corte al cerrar. Bloquea edición retroactiva.
 * - manifiesto_pdf_path: ruta del PDF generado.
 * - snapshot_json: totales congelados al cierre para no depender de queries futuras.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropi_cortes', function (Blueprint $table) {
            $table->string('manifiesto_hash', 64)->nullable()->after('cerrado_at');
            $table->string('manifiesto_pdf_path', 255)->nullable()->after('manifiesto_hash');
            $table->json('snapshot_json')->nullable()->after('manifiesto_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('dropi_cortes', function (Blueprint $table) {
            $table->dropColumn(['manifiesto_hash', 'manifiesto_pdf_path', 'snapshot_json']);
        });
    }
};
