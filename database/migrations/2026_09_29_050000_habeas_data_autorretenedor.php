<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * QA-FIX #9 · Habeas Data (Ley 1581/2012) + Autorretenedor.
 *   whatsapp_opt_in         · consentimiento explícito para WhatsApp comercial
 *   whatsapp_opt_in_at      · timestamp del consentimiento (trazabilidad SIC)
 *   whatsapp_opt_out_at     · si el contacto pide baja
 *   es_autorretenedor       · el proveedor retiene su propio retefuente → NO se le aplica
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->boolean('whatsapp_opt_in')->default(false)->after('telefono');
            $t->timestamp('whatsapp_opt_in_at')->nullable()->after('whatsapp_opt_in');
            $t->timestamp('whatsapp_opt_out_at')->nullable()->after('whatsapp_opt_in_at');
            $t->boolean('es_autorretenedor')->default(false)->after('regimen_iva');
        });
    }

    public function down(): void
    {
        Schema::table('contactos', function (Blueprint $t) {
            $t->dropColumn(['whatsapp_opt_in', 'whatsapp_opt_in_at', 'whatsapp_opt_out_at', 'es_autorretenedor']);
        });
    }
};
