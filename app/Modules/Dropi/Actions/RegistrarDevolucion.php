<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\DestinoDevolucion;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

/**
 * §12 Diseño Dropi — Registrar devolución de pedido completo.
 *  - El alistador escanea la guía o la busca.
 *  - Sistema trae contexto: productos, cliente, remisión/factura.
 *  - Alistador decide destino de inventario (§12).
 *  - Pedido queda marcado como "devuelto" y "no cobrar" (elimina falso pendiente en conciliación §15).
 *  - Si tiene factura ARI, se marca para nota crédito (posible gracias a la remisión 1:1 §21).
 */
class RegistrarDevolucion
{
    use AsAction;

    /**
     * @return array{devolucion:DropiDevolucion, movimientos:int}
     */
    public function handle(
        string $guia,
        DestinoDevolucion $destino,
        int $userId,
        ?string $notas = null,
    ): array {
        return DB::transaction(function () use ($guia, $destino, $userId, $notas) {
            $pedido = DropiPedido::where('guia', $guia)->firstOrFail();

            if ($pedido->devolucion) {
                throw new RuntimeException("La guía {$guia} ya tiene una devolución registrada.");
            }

            // NO pre-setear genero_nota_credito — el observer EmitirNotaCreditoDropi
            // lo activa recién cuando confirma dispatch a SIIGO. Antes mentíamos.
            $devolucion = DropiDevolucion::create([
                'pedido_id' => $pedido->id,
                'recibido_at' => now(),
                'destino_inventario' => $destino->value,
                'decision_por' => $userId,
                'genero_nota_credito' => false,
                'notas' => $notas,
            ]);

            // Reingresar inventario si aplica §12
            $movimientos = 0;
            if ($destino === DestinoDevolucion::Reingreso) {
                $movimientos = $this->reingresarInventario($pedido, $destino, $userId);
            } elseif ($destino !== DestinoDevolucion::BajaTotal) {
                // Averías: entra a la zona correspondiente (no vendible pero rastreable)
                $movimientos = $this->reingresarInventario($pedido, $destino, $userId);
            }
            // BajaTotal: no crea movimientos — se pierde

            // Cambio de estado del pedido
            $anterior = $pedido->estado->value;
            $pedido->update([
                'estado' => EstadoPedidoDropi::Devuelto,
                'devuelto_at' => now(),
            ]);

            DropiEstadoBitacora::create([
                'pedido_id' => $pedido->id,
                'estado_desde' => $anterior,
                'estado_hasta' => EstadoPedidoDropi::Devuelto->value,
                'fuente' => 'manual',
                'user_id' => $userId,
                'payload' => ['motivo' => 'devolucion_recibida', 'destino' => $destino->value],
            ]);

            return ['devolucion' => $devolucion, 'movimientos' => $movimientos];
        });
    }

    protected function reingresarInventario(DropiPedido $pedido, DestinoDevolucion $destino, int $userId): int
    {
        $ubicacion = InventarioUbicacion::query()
            ->where('categoria', $destino->categoriaUbicacion()->value)
            ->where('activa', true)
            ->first();

        if (! $ubicacion) {
            return 0;
        }

        $movimientos = 0;
        foreach ($pedido->items as $item) {
            if (! $item->variante_id) {
                continue;
            }

            InventarioMovimiento::create([
                'variante_id' => $item->variante_id,
                'ubicacion_id' => $ubicacion->id,
                'tipo' => 'ingreso',
                'cantidad' => (int) $item->cantidad,
                'referencia_tipo' => 'devolucion',
                'referencia_id' => $pedido->id,
                'user_id' => $userId,
                'notas' => "Devolución guía {$pedido->guia} → {$destino->label()}",
            ]);
            $movimientos++;
        }

        return $movimientos;
    }
}
