<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cierra registros de empaque en_curso abandonados (browser cerrado, operario cambió de turno).
 * Los marca como 'abandonado' y devuelve el pedido a Pending si no completó.
 * Programado cada 15 min en console/kernel.
 */
class CerrarEmpaquesHuerfanos extends Command
{
    protected $signature = 'empaque:cerrar-huerfanos {--minutos=30 : Cerrar registros con más de N minutos sin actividad}';

    protected $description = 'Cierra registros de empaque en_curso abandonados por más de N minutos';

    public function handle(): int
    {
        $minutos = (int) $this->option('minutos');
        $limite = now()->subMinutes($minutos);

        $huerfanos = EmpaqueRegistro::where('estado', 'en_curso')
            ->where('inicio_at', '<=', $limite)
            ->get();

        if ($huerfanos->isEmpty()) {
            $this->info("Sin registros huérfanos (más de {$minutos} min sin actividad).");
            return self::SUCCESS;
        }

        $cerrados = 0;
        $pedidosReset = 0;

        DB::transaction(function () use ($huerfanos, &$cerrados, &$pedidosReset) {
            foreach ($huerfanos as $r) {
                $r->update([
                    'estado' => 'abandonado',
                    'fin_at' => now(),
                    'notas' => trim(($r->notas ?? '') . "\n[auto] cerrado por inactividad > {$this->option('minutos')} min"),
                ]);
                $cerrados++;

                // Si el pedido quedó en Alistando y ningún ítem se pickeó completo, volver a Pending
                $pedido = DropiPedido::find($r->pedido_id);
                if ($pedido && $pedido->estado === EstadoPedidoDropi::Alistando) {
                    $totalPickeado = $pedido->items()->where('cantidad_pickeada', '>', 0)->count();
                    if ($totalPickeado === 0) {
                        $pedido->update(['estado' => EstadoPedidoDropi::Pending]);
                        $pedidosReset++;
                    }
                }
            }
        });

        $this->info("Cerrados {$cerrados} registros huérfanos, {$pedidosReset} pedidos devueltos a Pending.");
        return self::SUCCESS;
    }
}
