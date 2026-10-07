<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UBIC-3 · Resolución SIIGO y cuentas PUC por ubicación.
 *
 * Permite que:
 *   - Cada ubicación tenga su propia resolución DIAN para facturar venta.
 *     El payload /v1/invoices usa `document.id = siigo_resolution_id`.
 *   - Las cuentas de inventario/costo salgan por ubicación en vez de ser
 *     globales (resuelve también BUG-CTA · el asiento SIIGO de un traslado
 *     entre bodegas con cuentas distintas deja de tener DB=CR a la misma
 *     cuenta 1435 global y refleja el movimiento real inter-bodegas).
 *
 * El `prefijo` y `siigo_resolution_name` se cachean para mostrar en UI sin
 * necesidad de pegarle a /v1/document-types en cada paint.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $table) {
            if (! Schema::hasColumn('inventario_ubicaciones', 'siigo_resolution_id')) {
                $table->unsignedBigInteger('siigo_resolution_id')->nullable()->after('siigo_id')->index();
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'siigo_resolution_name')) {
                $table->string('siigo_resolution_name', 120)->nullable()->after('siigo_resolution_id');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'siigo_resolution_prefix')) {
                $table->string('siigo_resolution_prefix', 10)->nullable()->after('siigo_resolution_name');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'cta_inventario')) {
                $table->string('cta_inventario', 10)->nullable()->after('siigo_resolution_prefix')
                    ->comment('PUC inventario propio de la ubicación · si NULL cae al global 1435');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'cta_costo')) {
                $table->string('cta_costo', 10)->nullable()->after('cta_inventario')
                    ->comment('PUC costo propio de la ubicación · si NULL cae al global 6135');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'direccion')) {
                $table->string('direccion', 200)->nullable()->after('notas');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'ciudad')) {
                $table->string('ciudad', 80)->nullable()->after('direccion');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'responsable_user_id')) {
                $table->foreignId('responsable_user_id')->nullable()->after('ciudad')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $table) {
            if (Schema::hasColumn('inventario_ubicaciones', 'responsable_user_id')) {
                $table->dropConstrainedForeignId('responsable_user_id');
            }
            foreach (['ciudad', 'direccion', 'cta_costo', 'cta_inventario', 'siigo_resolution_prefix', 'siigo_resolution_name'] as $c) {
                if (Schema::hasColumn('inventario_ubicaciones', $c)) $table->dropColumn($c);
            }
            if (Schema::hasColumn('inventario_ubicaciones', 'siigo_resolution_id')) {
                $table->dropIndex(['siigo_resolution_id']);
                $table->dropColumn('siigo_resolution_id');
            }
        });
    }
};
