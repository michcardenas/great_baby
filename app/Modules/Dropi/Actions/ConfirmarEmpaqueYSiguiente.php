<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class ConfirmarEmpaqueYSiguiente
{
    use AsAction;

    /**
     * Cierra el registro de empaque, cambia estado a Empacado y retorna las métricas.
     * Bloquea si aún faltan unidades por pickear.
     *
     * @return array{duracion_seg:int, items:int, msg:string}
     */
    public function handle(int $pedidoId, int $operarioId): array
    {
        return DB::transaction(function () use ($pedidoId, $operarioId) {
            $pedido = DropiPedido::with('items')->lockForUpdate()->findOrFail($pedidoId);

            // Bloqueo: no confirmar si faltan unidades
            $itemsPendientes = $pedido->items->filter(function ($it) {
                $req = (int) ($it->cantidad ?? 1);
                $ya = (int) ($it->cantidad_pickeada ?? 0);
                return $ya < $req;
            });

            if ($itemsPendientes->isNotEmpty()) {
                $faltan = $itemsPendientes->sum(fn ($it) => ((int) ($it->cantidad ?? 1)) - ((int) ($it->cantidad_pickeada ?? 0)));
                throw new InvalidArgumentException("Aún faltan {$faltan} unidad(es) por escanear.");
            }

            $registro = EmpaqueRegistro::where('pedido_id', $pedidoId)
                ->where('estado', 'en_curso')
                ->latest('inicio_at')
                ->lockForUpdate()
                ->first();

            if (! $registro) {
                throw new InvalidArgumentException('No hay empaque en curso para este pedido.');
            }

            $fin = now();
            $duracion = (int) $registro->inicio_at->diffInSeconds($fin, absolute: true);

            $totalUnidades = (int) $pedido->items->sum(fn ($it) => (int) ($it->cantidad ?? 1));

            $registro->update([
                'fin_at' => $fin,
                'estado' => 'completado',
                'duracion_segundos' => $duracion,
                'items_escaneados' => $totalUnidades,
                'items_totales' => $totalUnidades,
            ]);

            $pedido->transicionar(
                EstadoPedidoDropi::Empacado,
                'manual',
                $operarioId,
                ['origen' => 'estacion_empaque', 'duracion_seg' => $duracion, 'items' => $totalUnidades],
            );

            return [
                'duracion_seg' => $duracion,
                'items' => $pedido->items->count(),
                'msg' => "Pedido {$pedido->guia} empacado en " . gmdate('i:s', $duracion),
            ];
        });
    }
}
