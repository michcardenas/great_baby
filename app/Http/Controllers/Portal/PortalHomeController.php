<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PortalHomeController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    public function __invoke(Request $request): Response
    {
        $cliente = $request->user('cliente');

        // QA-D Bloque3: cache 60s por cliente. Invalidación al facturar / crear pedido / registrar pago.
        $kpis = Cache::remember("portal.kpis.{$cliente->id}", 60, function () use ($cliente) {
            // QA-D Bloque3: timezone Bogotá explícito (evita cambiar frontera 5h antes).
            $inicioMes = now('America/Bogota')->startOfMonth();
            $finMes = now('America/Bogota')->endOfMonth();

            return [
                'pedidos_pendientes' => (int) PedidoCliente::where('contacto_id', $cliente->id)
                    ->whereIn('estado', ['borrador', 'enviado', 'aprobado'])->count(),
                'pedidos_mes' => (int) PedidoCliente::where('contacto_id', $cliente->id)
                    ->whereBetween('created_at', [$inicioMes, $finMes])->count(),
                'saldo' => (float) FacturaVenta::where('contacto_id', $cliente->id)
                    ->whereIn('estado', ['pendiente', 'abonada', 'vencida'])->sum('saldo'),
                'facturas_vencidas' => (int) FacturaVenta::where('contacto_id', $cliente->id)
                    ->where('estado', 'vencida')->count(),
            ];
        });

        $ultimos = PedidoCliente::where('contacto_id', $cliente->id)
            ->orderByDesc('id')->limit(5)->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'numero' => $p->numero,
                'estado' => $p->estado,
                'total' => (float) $p->total,
                'fecha' => $p->created_at?->format('Y-m-d'),
            ])->all();

        return Inertia::render('Portal/Dashboard', [
            'kpis' => $kpis,
            'ultimos_pedidos' => $ultimos,
        ]);
    }
}
