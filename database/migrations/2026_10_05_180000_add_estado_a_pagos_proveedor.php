<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * COMP-B5 · Pago proveedor requiere confirmación.
 *
 * Antes: crear pago encolaba push a SIIGO inmediato. Un typo (NIT, monto,
 * cuenta) se replicaba al libro contable del cliente y había que anularlo
 * allá. Ahora el pago nace `pendiente` y solo cuando Aracely lo confirma
 * se dispara el push. Backfill: los existentes quedan `confirmado` para
 * no romper el histórico.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pagos_proveedor', function (Blueprint $t) {
            $t->string('estado', 20)->default('pendiente')->after('metodo');
            $t->timestamp('confirmado_at')->nullable()->after('estado');
            $t->foreignId('confirmado_por')->nullable()->after('confirmado_at')->constrained('users');
            $t->index('estado');
        });
        DB::table('pagos_proveedor')->whereNull('confirmado_at')->update([
            'estado' => 'confirmado',
            'confirmado_at' => DB::raw('created_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('pagos_proveedor', function (Blueprint $t) {
            $t->dropConstrainedForeignId('confirmado_por');
            $t->dropColumn(['estado', 'confirmado_at']);
        });
    }
};
