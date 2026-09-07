<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix seguridad — idempotencia real de wallet.
 * Antes: (tipo,fecha,monto,guia) → dos pagos legítimos iguales se colapsaban.
 * Ahora: usamos el ID que Dropi asigna al movimiento como llave única.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->string('dropi_movimiento_id', 60)->nullable()->after('id');
            $table->unique('dropi_movimiento_id');
        });
    }

    public function down(): void
    {
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->dropUnique(['dropi_movimiento_id']);
            $table->dropColumn('dropi_movimiento_id');
        });
    }
};
