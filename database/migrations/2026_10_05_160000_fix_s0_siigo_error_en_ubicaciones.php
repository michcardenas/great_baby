<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $t) {
            // FIX-S0 · auditoría del push a SIIGO · si el job falló, qué pasó.
            // El UI pinta "falló: {mensaje}" en vez de dejar al usuario sin saber.
            // Añadir también siigo_sync_at si no existía (dependiente de la
            // migración UBIC-9 que originalmente lo asumía).
            if (! Schema::hasColumn('inventario_ubicaciones', 'siigo_sync_at')) {
                $t->timestamp('siigo_sync_at')->nullable()->after('siigo_resolution_prefix');
            }
            if (! Schema::hasColumn('inventario_ubicaciones', 'siigo_last_error')) {
                $t->string('siigo_last_error', 500)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('inventario_ubicaciones', function (Blueprint $t) {
            $t->dropColumn('siigo_last_error');
        });
    }
};
