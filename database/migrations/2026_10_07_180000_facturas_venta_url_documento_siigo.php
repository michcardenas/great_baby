<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el enlace al documento impreso de SIIGO.
 *
 * Al emitir, SIIGO devuelve `public_url`: la factura lista para ver, imprimir
 * o mandarle al cliente. El ERP la descartaba, así que para imprimir había que
 * entrar a SIIGO y buscarla a mano — y como la numeración es distinta de la
 * del ERP (FV-261007-0004 acá, FV-247-1215 allá), ni siquiera era obvio cuál.
 *
 * El número de SIIGO ya se guardaba en `numero_siigo`; lo que faltaba era el
 * enlace y mostrarlos a quien factura.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (! Schema::hasColumn('facturas_venta', 'siigo_public_url')) {
                $t->string('siigo_public_url', 500)->nullable()->after('siigo_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('facturas_venta', function (Blueprint $t) {
            if (Schema::hasColumn('facturas_venta', 'siigo_public_url')) {
                $t->dropColumn('siigo_public_url');
            }
        });
    }
};
