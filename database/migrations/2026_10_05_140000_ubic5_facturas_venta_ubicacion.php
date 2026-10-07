<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UBIC-5 · ubicacion_id en facturas_venta.
 *
 * Permite trazar desde qué ubicación/bodega salió una factura. Con eso,
 * SiigoEmisionService::construirPayload resuelve la resolución DIAN:
 *   - Si la ubicación tiene siigo_resolution_id → usa ese document.id.
 *   - Si no, fallback a SiigoConfig::tipo_documento_id (comportamiento previo).
 *
 * Para cartera B2B, el GenerarFacturasB2BDeCorte resuelve la ubicación origen
 * desde el pedido Dropi antes de crear la FacturaVenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $table) {
            if (! Schema::hasColumn('facturas_venta', 'ubicacion_id')) {
                $table->foreignId('ubicacion_id')->nullable()
                    ->constrained('inventario_ubicaciones')->nullOnDelete()
                    ->after('contacto_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas_venta', function (Blueprint $table) {
            if (Schema::hasColumn('facturas_venta', 'ubicacion_id')) {
                $table->dropConstrainedForeignId('ubicacion_id');
            }
        });
    }
};
