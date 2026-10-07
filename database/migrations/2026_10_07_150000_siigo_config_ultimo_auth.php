<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guarda el resultado del último intento de autenticación contra SIIGO.
 *
 * La pantalla de integración mostraba «✅ Activa» en verde leyendo la casilla
 * `activo`, que sólo dice "alguien prendió el interruptor". Con credenciales
 * vencidas el ERP no mandaba nada a SIIGO y el panel seguía en verde: se
 * comprobó pidiendo "Probar conexión" y viendo el aviso «HTTP 401» encima de
 * la tarjeta verde. Con esto el estado pasa a reflejar lo que de verdad
 * contesta SIIGO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siigo_config', function (Blueprint $t) {
            if (! Schema::hasColumn('siigo_config', 'ultimo_auth_ok')) {
                $t->boolean('ultimo_auth_ok')->nullable()->after('token_expires_at');
            }
            if (! Schema::hasColumn('siigo_config', 'ultimo_auth_at')) {
                $t->timestamp('ultimo_auth_at')->nullable()->after('ultimo_auth_ok');
            }
            if (! Schema::hasColumn('siigo_config', 'ultimo_auth_error')) {
                $t->string('ultimo_auth_error', 200)->nullable()->after('ultimo_auth_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siigo_config', function (Blueprint $t) {
            foreach (['ultimo_auth_ok', 'ultimo_auth_at', 'ultimo_auth_error'] as $c) {
                if (Schema::hasColumn('siigo_config', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
