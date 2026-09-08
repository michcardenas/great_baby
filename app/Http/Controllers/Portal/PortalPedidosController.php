<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PortalPedidosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    public function index(Request $request): Response
    {
        $cliente = $request->user('cliente');
        $estado = (string) $request->input('estado', '');

        // QA-D Bloque3: withCount evita N+1 (antes items()->count() por pedido).
        $pedidos = PedidoCliente::where('contacto_id', $cliente->id)
            ->withCount('items')
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('id')->paginate(20)
            ->withQueryString();

        $conteos = PedidoCliente::where('contacto_id', $cliente->id)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')->pluck('total', 'estado')->all();

        return Inertia::render('Portal/Pedidos/Index', [
            'pedidos' => $pedidos->through(fn ($p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'estado' => $p->estado,
                'total' => (float) $p->total,
                'fecha' => $p->created_at?->format('Y-m-d H:i'),
                'items_count' => (int) $p->items_count,
            ]),
            'conteos' => $conteos,
            'estado_filtro' => $estado ?: null,
        ]);
    }

    public function show(Request $request, int $pedido): Response
    {
        $cliente = $request->user('cliente');

        $p = PedidoCliente::with(['items', 'factura:id,numero,total,estado', 'facturadoPor:id,name'])
            ->where('contacto_id', $cliente->id)
            ->findOrFail($pedido);

        return Inertia::render('Portal/Pedidos/Show', [
            'pedido' => [
                'id' => $p->id,
                'numero' => $p->numero,
                'estado' => $p->estado,
                'subtotal' => (float) $p->subtotal,
                'iva' => (float) $p->iva,
                'total' => (float) $p->total,
                'notas_cliente' => $p->notas_cliente,
                'motivo_rechazo' => $p->motivo_rechazo,
                'creado' => $p->created_at?->format('Y-m-d H:i'),
                'enviado_at' => $p->enviado_at?->format('Y-m-d H:i'),
                'aprobado_at' => $p->aprobado_at?->format('Y-m-d H:i'),
                'rechazado_at' => $p->rechazado_at?->format('Y-m-d H:i'),
                'facturado_at' => $p->facturado_at?->format('Y-m-d H:i'),
                'facturado_por' => $p->facturadoPor?->name,
                'factura' => $p->factura ? [
                    'id' => $p->factura->id,
                    'numero' => $p->factura->numero,
                    'total' => (float) $p->factura->total,
                    'estado' => is_object($p->factura->estado) ? $p->factura->estado->value : $p->factura->estado,
                ] : null,
            ],
            'items' => $p->items->map(fn ($i) => [
                'sku' => $i->sku_snapshot,
                'desc' => $i->descripcion_snapshot,
                'cantidad' => (int) $i->cantidad,
                'precio' => (float) $i->precio_unitario,
                'subtotal' => (float) $i->subtotal,
                'iva' => (float) $i->iva_valor,
                'total' => (float) $i->total,
            ])->values(),
        ]);
    }
}
