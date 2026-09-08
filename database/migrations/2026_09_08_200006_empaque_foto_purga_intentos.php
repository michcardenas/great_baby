<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sec F7 auditor R3: si Storage::delete falla permanentemente (fs read-only, ACL rota),
 * el cron re-intentaba la misma foto todos los días → loop infinito de warnings + FAILURE.
 * Agregamos contador de intentos y timestamp del último fallo para descartar tras N.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empaques_registro', function (Blueprint $t) {
            if (! Schema::hasColumn('empaques_registro', 'foto_purga_intentos')) {
                $t->unsignedTinyInteger('foto_purga_intentos')->default(0)->after('foto_at');
                $t->timestamp('foto_purga_ultimo_fallo_at')->nullable()->after('foto_purga_intentos');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empaques_registro', function (Blueprint $t) {
            if (Schema::hasColumn('empaques_registro', 'foto_purga_intentos')) {
                $t->dropColumn(['foto_purga_intentos', 'foto_purga_ultimo_fallo_at']);
            }
        });
    }
};
