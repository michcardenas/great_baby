<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class MapaColombiaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esAracely(), 403);
            return $next($r);
        })];
    }

    public function __invoke(Request $request): Response
    {
        $desde = $request->input('desde', now('America/Bogota')->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now('America/Bogota')->endOfDay()->toDateString());

        $data = Cache::remember("mapa.{$desde}.{$hasta}", 600, function () use ($desde, $hasta) {
            // Ventas Dropi por departamento
            $porDepto = DropiPedido::whereBetween('pagado_at', [$desde, $hasta])
                ->where('estado', 'pagado')
                ->selectRaw('cliente_depto, COUNT(*) as pedidos, SUM(monto_esperado_proveedor) as total')
                ->whereNotNull('cliente_depto')
                ->groupBy('cliente_depto')->get();

            $porCiudad = DropiPedido::whereBetween('pagado_at', [$desde, $hasta])
                ->where('estado', 'pagado')
                ->selectRaw('cliente_ciudad, cliente_depto, COUNT(*) as pedidos, SUM(monto_esperado_proveedor) as total')
                ->whereNotNull('cliente_ciudad')
                ->groupBy('cliente_ciudad', 'cliente_depto')->orderByDesc('total')->limit(30)->get();

            return [
                'departamentos' => $porDepto->map(fn ($r) => [
                    'nombre' => strtoupper(trim($r->cliente_depto)),
                    'pedidos' => (int) $r->pedidos,
                    'total' => (float) $r->total,
                ])->all(),
                'ciudades' => $porCiudad->map(fn ($r) => [
                    'nombre' => $r->cliente_ciudad,
                    'depto' => $r->cliente_depto,
                    'pedidos' => (int) $r->pedidos,
                    'total' => (float) $r->total,
                ])->all(),
                'total_ventas' => (float) $porDepto->sum('total'),
                'total_pedidos' => (int) $porDepto->sum('pedidos'),
            ];
        });

        return Inertia::render('Mapa/Colombia', [
            ...$data,
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
        ]);
    }
}
