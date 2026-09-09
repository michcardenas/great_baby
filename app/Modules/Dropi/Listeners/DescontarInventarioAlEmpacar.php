<?php

namespace App\Modules\Dropi\Listeners;

use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Events\PedidoDropiTransicionado;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * P3 · Al pasar un pedido Dropi a Empacado, descuenta la mercancía del stock
 * de venta. Idempotente por (referencia_tipo, referencia_id): si el listener
 * corre dos veces sobre el mismo pedido (retry job, doble evento), no duplica
 * el movimiento.
 *
 * Se ejecuta síncrono dentro de la transacción del cambio de estado (afterCommit
 * no aplica: queremos rollback si falla el kardex).
 *
 * Re-audit M3 PATRÓN ι (FUNC-C4) · BUG GRAVE previo: insertaba `cantidad`
 *   POSITIVA con `tipo='egreso'`, mientras que el resto del sistema convención
 *   es "cantidad con signo" (SUM(cantidad) = saldo físico) — cada guía Dropi
 *   SUMABA stock. Fix: cantidad negativa para egreso. + lockForUpdate sobre
 *   kardex por variante+ubicación antes de escribir, para evitar carreras
 *   con conteo/traslado concurrente.
 */
class DescontarInventarioAlEmpacar
{
    public const REFERENCIA_TIPO = 'dropi_pedido_egreso';

    public function handle(PedidoDropiTransicionado $e): void
    {
        if ($e->estadoHasta !== EstadoPedidoDropi::Empacado) {
            return;
        }

        DB::transaction(function () use ($e) {
            $pedido = DropiPedido::with('items.variante')->find($e->pedido->id);
            if (! $pedido) return;

            // Re-audit H4 datos · guard idempotente por diferencia egresos−reversos.
            // Antes solo miraba `yaExisten`, así un rework Empacado→Alistando→Empacado
            // dejaba el kardex sin actualizar. Ahora contamos: si la diferencia
            // entre egresos y reversos ya cubrió esta transición, no re-descontamos;
            // si el rework generó un reverso previo, sí volvemos a descontar.
            $egresos = InventarioMovimiento::where('referencia_tipo', self::REFERENCIA_TIPO)
                ->where('referencia_id', $pedido->id)->count();
            $reversos = InventarioMovimiento::where(
                'referencia_tipo',
                \App\Modules\Dropi\Listeners\RevertirEgresoAlDesempacar::REFERENCIA_TIPO
            )->where('referencia_id', $pedido->id)->count();
            if ($egresos > $reversos) return;

            // Ubicación de venta activa (fallback: primera activa disponible para venta).
            $ubicacion = InventarioUbicacion::where('categoria', CategoriaUbicacion::Venta->value)
                ->where('activa', true)
                ->orderBy('id')
                ->first();

            if (! $ubicacion) {
                Log::warning('Dropi: no hay ubicación de venta activa para descontar empaque', [
                    'pedido_id' => $pedido->id, 'guia' => $pedido->guia,
                ]);
                return;
            }

            foreach ($pedido->items as $item) {
                if (! $item->variante_id) continue;
                $cantidad = (int) ($item->cantidad ?? 0);
                if ($cantidad <= 0) continue;

                // PATRÓN ι · lockForUpdate sobre kardex (variante,ubicación) para
                //   serializar contra conteo/traslado concurrente.
                InventarioMovimiento::query()
                    ->where('variante_id', $item->variante_id)
                    ->where('ubicacion_id', $ubicacion->id)
                    ->lockForUpdate()->get();

                InventarioMovimiento::create([
                    'variante_id' => $item->variante_id,
                    'ubicacion_id' => $ubicacion->id,
                    'tipo' => 'egreso',
                    // PATRÓN ι · SIGNO NEGATIVO. Antes: cantidad positiva con
                    //   tipo=egreso sumaba stock. SUM(cantidad) es saldo físico.
                    'cantidad' => -$cantidad,
                    'referencia_tipo' => self::REFERENCIA_TIPO,
                    'referencia_id' => $pedido->id,
                    'user_id' => $e->userId,
                    'notas' => "Empaque guía {$pedido->guia}",
                ]);
            }
        });
    }
}
