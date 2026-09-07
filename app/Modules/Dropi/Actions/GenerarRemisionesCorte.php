<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiRemision;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §21 Diseño Dropi — Facturación interna + exportación a ARI.
 *
 * DECISIÓN CLAVE: remisión 1:1 por pedido (no por lote de corte). §2.
 * Al cerrar el corte, se genera una remisión por cada pedido despachado.
 * Luego se envía por lote a ARI (endpoint bulk), preservando trazabilidad 1:1 para NC.
 *
 * IMPORTANTE §21: la remisión SOLO refleja el valor de proveedor GB del catálogo,
 * NO el total al cliente (que incluye ganancia vendedor + flete transportadora).
 */
class GenerarRemisionesCorte
{
    use AsAction;

    /**
     * @return array{corte:int, remisiones_creadas:int, valor_lote:float}
     */
    public function handle(int $corteId): array
    {
        return DB::transaction(function () use ($corteId) {
            $corte = DropiCorte::findOrFail($corteId);

            $pedidos = DropiPedido::query()
                ->where('corte_id', $corteId)
                ->whereIn('estado', [
                    EstadoPedidoDropi::Despachado,
                    EstadoPedidoDropi::Entregado,
                    EstadoPedidoDropi::Pagado,
                ])
                ->whereDoesntHave('remision')
                ->get();

            $creadas = 0;
            $valorLote = 0.0;

            foreach ($pedidos as $pedido) {
                $consecutivo = $this->siguienteConsecutivo();

                DropiRemision::create([
                    'pedido_id' => $pedido->id,
                    'consecutivo' => $consecutivo,
                    'valor_proveedor' => $pedido->monto_esperado_proveedor,
                ]);

                $creadas++;
                $valorLote += (float) $pedido->monto_esperado_proveedor;
            }

            return [
                'corte' => $corte->id,
                'remisiones_creadas' => $creadas,
                'valor_lote' => $valorLote,
            ];
        });
    }

    protected function siguienteConsecutivo(): string
    {
        // Lock pesimista: dos cierres de corte simultáneos no generan el mismo consecutivo.
        $ultimo = DropiRemision::query()->orderByDesc('id')->lockForUpdate()->first();
        $nro = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo->consecutivo)) + 1 : 1;

        return 'REM-DP-' . str_pad((string) $nro, 6, '0', STR_PAD_LEFT);
    }
}
