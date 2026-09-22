<?php

namespace App\Modules\Dropi\Listeners;

use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Events\PedidoDropiTransicionado;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Support\Facades\DB;

/**
 * Re-audit H4 datos · rework `Empacado → Alistando` sin este listener dejaba
 * el kardex mintiendo: el egreso original quedaba escrito y el `yaExisten`
 * guard de `DescontarInventarioAlEmpacar` cortaba el 2° egreso al re-empacar
 * con distinta cantidad/SKUs.
 *
 * Este listener crea un INGRESO compensatorio (mismo material, misma cantidad)
 * marcado como `dropi_pedido_egreso_reverso`. Idempotente: si ya existe el
 * reverso para el pedido, no re-crea.
 *
 * Se dispara con el evento PedidoDropiTransicionado cuando estadoDesde=Empacado
 * y estadoHasta=Alistando.
 */
class RevertirEgresoAlDesempacar
{
    public const REFERENCIA_TIPO = 'dropi_pedido_egreso_reverso';

    public function handle(PedidoDropiTransicionado $e): void
    {
        if ($e->estadoDesde !== EstadoPedidoDropi::Empacado
            || $e->estadoHasta !== EstadoPedidoDropi::Alistando) {
            return;
        }

        DB::transaction(function () use ($e) {
            $pedido = DropiPedido::with('items.variante')->find($e->pedido->id);
            if (! $pedido) return;

            // Re-audit DR-ε (FUNC-C4) · antes: `exists()` bloqueaba el 2º
            //   reverso en rework múltiple (Empacado→Alistando→Empacado→Alistando)
            //   dejando kardex a -N. Ahora balanceamos por conteo, igual que
            //   el listener de egreso: si ya hay tantos reversos como egresos,
            //   no rehacer; si el rework generó otro egreso desbalanceado,
            //   revertir de nuevo.
            $egresos = InventarioMovimiento::where(
                'referencia_tipo',
                \App\Modules\Dropi\Listeners\DescontarInventarioAlEmpacar::REFERENCIA_TIPO
            )->where('referencia_id', $pedido->id)->count();
            $reversos = InventarioMovimiento::where('referencia_tipo', self::REFERENCIA_TIPO)
                ->where('referencia_id', $pedido->id)->count();
            if ($reversos >= $egresos) return;

            $ubicacion = InventarioUbicacion::where('categoria', CategoriaUbicacion::Venta->value)
                ->where('activa', true)->orderBy('id')->first();
            if (! $ubicacion) return;

            foreach ($pedido->items as $item) {
                if (! $item->variante_id) continue;
                $cantidad = (int) ($item->cantidad ?? 0);
                if ($cantidad <= 0) continue;

                InventarioMovimiento::create([
                    'variante_id' => $item->variante_id,
                    'ubicacion_id' => $ubicacion->id,
                    'tipo' => 'ingreso',
                    'cantidad' => $cantidad,
                    'referencia_tipo' => self::REFERENCIA_TIPO,
                    'referencia_id' => $pedido->id,
                    'user_id' => $e->userId,
                    'notas' => "Reverso de empaque guía {$pedido->guia} (rework)",
                ]);
            }
        });
    }
}
