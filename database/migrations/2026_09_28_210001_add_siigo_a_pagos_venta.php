<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F10 · siigo_id + siigo_number + siigo_sync_at en pagos_venta.
 * Cada pago cliente se refleja en SIIGO como un VOUCHER de ingreso
 * (recibo de caja RC) aplicado contra la factura de venta correspondiente.
 * Endpoint SIIGO: POST /v1/vouchers.
 *
 * Idempotente (Schema::hasColumn).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos_venta', function ($table) {
            if (! Schema::hasColumn('pagos_venta', 'siigo_id')) {
                $table->string('siigo_id', 60)->nullable();
            }
            if (! Schema::hasColumn('pagos_venta', 'siigo_number')) {
                $table->string('siigo_number', 60)->nullable();
            }
            if (! Schema::hasColumn('pagos_venta', 'siigo_sync_at')) {
                $table->timestamp('siigo_sync_at')->nullable();
            }
        });

        Schema::table('pagos_venta', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM pagos_venta WHERE Key_name = 'pagos_venta_siigo_id_uq'"
            ));
            if ($existente->isEmpty()) {
                $table->unique('siigo_id', 'pagos_venta_siigo_id_uq');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pagos_venta', function ($table) {
            $existente = collect(DB::select(
                "SHOW INDEXES FROM pagos_venta WHERE Key_name = 'pagos_venta_siigo_id_uq'"
            ));
            if ($existente->isNotEmpty()) {
                $table->dropUnique('pagos_venta_siigo_id_uq');
            }
            foreach (['siigo_sync_at', 'siigo_number', 'siigo_id'] as $col) {
                if (Schema::hasColumn('pagos_venta', $col)) $table->dropColumn($col);
            }
        });
    }
};
