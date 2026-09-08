<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REU-5: mercancía en tránsito (post-despacho pero sin novedad).
 * Alerta pedidos despachados hace más de `dropi.dias_retorno_mercancia` días
 * que siguen sin entregar/pagar/devolver.
 *
 * Aracely puede excluir por guía marcando `ignorar_alerta_transito=true` en el pedido.
 */
class AuditarMercanciaEnTransito
{
    use AsAction;

    public function handle(?int $diasMax = null, int $paginar = 500): array
    {
        $max = $diasMax ?? (int) setting('dropi.dias_retorno_mercancia', 20);
        $limite = now()->subDays($max);

        $q = DropiPedido::query()
            ->whereIn('estado', [
                EstadoPedidoDropi::Despachado,
                EstadoPedidoDropi::DevolucionEnCamino,
            ])
            ->whereNotNull('despachado_at')
            ->where('despachado_at', '<=', $limite)
            ->where('ignorar_alerta_transito', false);

        $total = (clone $q)->count();
        $montoTotal = (float) (clone $q)->sum('monto_esperado_proveedor');

        $items = $q->orderBy('despachado_at')
            ->limit($paginar)
            ->get(['id', 'guia', 'cliente_nombre', 'cliente_ciudad', 'transportadora', 'despachado_at', 'monto_esperado_proveedor'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'guia' => $p->guia,
                'cliente' => $p->cliente_nombre,
                'ciudad' => $p->cliente_ciudad,
                'transportadora' => $p->transportadora,
                'despachado_at' => $p->despachado_at?->toIso8601String(),
                'dias_atras' => $p->despachado_at ? (int) $p->despachado_at->diffInDays(now(), absolute: true) : 0,
                'monto' => (float) $p->monto_esperado_proveedor,
            ])->all();

        return [
            'total' => $total,
            'monto_total' => $montoTotal,
            'dias_max' => $max,
            'items' => $items,
            'items_mostrados' => count($items),
            'truncado' => $total > count($items),
        ];
    }

    public static function resumen(): array
    {
        $max = (int) setting('dropi.dias_retorno_mercancia', 20);
        $total = DropiPedido::query()
            ->whereIn('estado', [EstadoPedidoDropi::Despachado, EstadoPedidoDropi::DevolucionEnCamino])
            ->whereNotNull('despachado_at')
            ->where('despachado_at', '<=', now()->subDays($max))
            ->where('ignorar_alerta_transito', false)
            ->count();
        return ['total' => $total, 'dias_max' => $max];
    }
}
