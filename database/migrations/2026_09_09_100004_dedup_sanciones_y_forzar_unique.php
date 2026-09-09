<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Re-audit H2 datos · La migración anterior envolvió `->unique(['pedido_id','tipo'])`
 * en try/catch para tolerar duplicados legacy. Si existían duplicados, el índice
 * NO se creó silenciosamente → ConciliarWalletDropi puede loopear al duplicar
 * la sanción.
 *
 * Aquí:
 *   1) auditar duplicados (pedido_id, tipo) en dropi_sanciones
 *   2) mergear duplicados: conservar el más antiguo, sumar diferencia
 *   3) crear el UNIQUE sin try/catch — falla ruidosa si no puede
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Detectar y consolidar duplicados.
        $duplicados = DB::table('dropi_sanciones')
            ->select('pedido_id', 'tipo', DB::raw('COUNT(*) as total'))
            ->groupBy('pedido_id', 'tipo')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicados as $d) {
            $filas = DB::table('dropi_sanciones')
                ->where('pedido_id', $d->pedido_id)
                ->where('tipo', $d->tipo)
                ->orderBy('id')
                ->get();

            $conservar = $filas->first();
            $diferenciaTotal = (float) $filas->sum('diferencia');
            $montoRecibidoTotal = (float) $filas->sum('monto_recibido');

            // Consolidar sobre la primera.
            DB::table('dropi_sanciones')
                ->where('id', $conservar->id)
                ->update([
                    'diferencia' => $diferenciaTotal,
                    'monto_recibido' => $montoRecibidoTotal,
                ]);

            // Borrar el resto.
            DB::table('dropi_sanciones')
                ->where('pedido_id', $d->pedido_id)
                ->where('tipo', $d->tipo)
                ->where('id', '!=', $conservar->id)
                ->delete();
        }

        // 2) Crear el UNIQUE si aún no existe — sin try/catch (fail loud).
        $existe = DB::selectOne("
            SELECT COUNT(*) AS c FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = 'dropi_sanciones'
              AND index_name = 'dropi_sanciones_pedido_tipo_unique'
        ");
        if ((int) ($existe->c ?? 0) === 0) {
            Schema::table('dropi_sanciones', function (Blueprint $table) {
                $table->unique(['pedido_id', 'tipo'], 'dropi_sanciones_pedido_tipo_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('dropi_sanciones', function (Blueprint $table) {
            try { $table->dropUnique('dropi_sanciones_pedido_tipo_unique'); } catch (\Throwable) {}
        });
    }
};
