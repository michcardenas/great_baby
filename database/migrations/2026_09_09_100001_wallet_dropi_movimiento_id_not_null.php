<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P7 · dropi_movimiento_id NO puede ser NULL — en MariaDB `UNIQUE(nullable)`
 * acepta N filas NULL y rompe la idempotencia. Para toda fila con NULL, se
 * asigna un ID sintetizado determinístico basado en (fecha|tipo|monto|guia|categoria)
 * y prefijo `synth:legacy:` para dejar rastro del origen.
 *
 * Además ampliamos la columna a 100 chars (los synth hashes son largos) y la
 * declaramos NOT NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Ampliar a 100 chars (los synth hashes son largos).
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->string('dropi_movimiento_id', 100)->nullable()->change();
        });

        // 2) Rellenar los NULLs actuales con un synth determinístico.
        $rows = DB::table('dropi_wallet_movimientos')
            ->whereNull('dropi_movimiento_id')
            ->select('id', 'fecha', 'tipo', 'monto', 'pedido_id', 'categoria')
            ->orderBy('id')
            ->get();

        foreach ($rows as $r) {
            $guia = $r->pedido_id
                ? (string) DB::table('dropi_pedidos')->where('id', $r->pedido_id)->value('guia')
                : '';
            $synth = 'synth:legacy:' . hash('sha256', implode('|', [
                (string) $r->fecha, (string) $r->tipo, (string) $r->monto,
                $guia, (string) ($r->categoria ?? ''), (string) $r->id,
            ]));
            DB::table('dropi_wallet_movimientos')
                ->where('id', $r->id)
                ->update(['dropi_movimiento_id' => $synth]);
        }

        // 3) Forzar NOT NULL.
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->string('dropi_movimiento_id', 100)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('dropi_wallet_movimientos', function (Blueprint $table) {
            $table->string('dropi_movimiento_id', 60)->nullable()->change();
        });
    }
};
