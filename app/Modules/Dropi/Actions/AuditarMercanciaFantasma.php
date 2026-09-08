<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REU-2: mercancía fantasma
 * Aracely: "Dropi la notifica como devolución pero nunca llega a bodega."
 *
 * Cruza `dropi_pedidos.devuelto_at` (llenado por SincronizarPedidosDropi desde el API
 * de Dropi — sin importar el estado) vs `dropi_devoluciones.recibido_at` (llenado por
 * RegistrarDevolucion cuando el paquete llega física a la bodega de GB).
 *
 * Bug F1 antes: filtrábamos por estado='devuelto', pero el sync NO cambia el estado
 * (línea 77: `$existente->estado`); solo RegistrarDevolucion lo pone → la alerta
 * NUNCA disparaba, era código muerto.
 */
class AuditarMercanciaFantasma
{
    use AsAction;

    public function handle(?int $diasTolerancia = null, int $paginar = 500): array
    {
        $tol = $diasTolerancia ?? (int) setting('dropi.dias_tolerancia_devolucion', 7);
        $limite = now()->subDays($tol);

        // Usar whereNotExists directo (no cargar 40k IDs en RAM) + paginación.
        $q = DropiPedido::query()
            ->whereNotNull('devuelto_at')
            ->where('devuelto_at', '<=', $limite)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('dropi_devoluciones')
                    ->whereColumn('dropi_devoluciones.pedido_id', 'dropi_pedidos.id')
                    ->whereNotNull('dropi_devoluciones.recibido_at')
                    ->whereNull('dropi_devoluciones.deleted_at');
            });

        $total = (clone $q)->count();
        $montoTotal = (float) (clone $q)->sum('monto_esperado_proveedor');

        $items = $q->orderByDesc('devuelto_at')
            ->limit($paginar)
            ->get(['id', 'guia', 'cliente_nombre', 'cliente_ciudad', 'transportadora', 'devuelto_at', 'monto_esperado_proveedor'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'guia' => $p->guia,
                'cliente' => $p->cliente_nombre,
                'ciudad' => $p->cliente_ciudad,
                'transportadora' => $p->transportadora,
                'devuelto_at' => $p->devuelto_at?->toIso8601String(),
                'dias_atras' => $p->devuelto_at ? (int) $p->devuelto_at->diffInDays(now(), absolute: true) : 0,
                'monto' => (float) $p->monto_esperado_proveedor,
            ])->all();

        return [
            'total' => $total,
            'monto_total' => $montoTotal,
            'dias_tolerancia' => $tol,
            'items' => $items,
            'items_mostrados' => count($items),
            'truncado' => $total > count($items),
        ];
    }

    /** Resumen barato para el Dashboard (solo conteo, sin cargar filas). */
    public static function resumen(): array
    {
        $tol = (int) setting('dropi.dias_tolerancia_devolucion', 7);
        $limite = now()->subDays($tol);

        $total = DropiPedido::query()
            ->whereNotNull('devuelto_at')
            ->where('devuelto_at', '<=', $limite)
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('dropi_devoluciones')
                    ->whereColumn('dropi_devoluciones.pedido_id', 'dropi_pedidos.id')
                    ->whereNotNull('dropi_devoluciones.recibido_at')
                    ->whereNull('dropi_devoluciones.deleted_at');
            })
            ->count();

        return ['total' => $total, 'dias_tolerancia' => $tol];
    }
}
