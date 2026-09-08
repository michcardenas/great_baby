<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\PagoVenta;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class PagosController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403);
                return $next($r);
            }),
        ];
    }

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));
        $medio = (string) $request->input('medio', 'todos');
        $desde = $request->input('desde');
        $hasta = $request->input('hasta');

        $query = PagoVenta::query()->with(['factura:id,numero,contacto_id', 'factura.contacto:id,nombre_completo', 'registrador:id,name']);

        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('referencia', 'like', "%{$q}%")
                    ->orWhere('banco', 'like', "%{$q}%")
                    ->orWhereHas('factura', fn ($f) => $f->where('numero', 'like', "%{$q}%"))
                    ->orWhereHas('factura.contacto', fn ($c) => $c->where('nombre_completo', 'like', "%{$q}%"));
            });
        }
        if ($medio !== 'todos') {
            $query->where('medio_pago', $medio);
        }
        if ($desde) $query->whereDate('fecha', '>=', $desde);
        if ($hasta) $query->whereDate('fecha', '<=', $hasta);

        $paginado = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(30);

        $totalAplicado = (float) (clone $query)->sum('monto_aplicado');
        $totalRecibido = (float) (clone $query)->sum('monto_recibido');

        return Inertia::render('Cartera/Pagos/Index', [
            'pagos' => $paginado->through(fn ($p) => [
                'id' => $p->id,
                'fecha' => $p->fecha?->toDateString(),
                'factura_id' => $p->factura?->id,
                'factura_numero' => $p->factura?->numero,
                'cliente' => $p->factura?->contacto?->nombre_completo,
                'medio_pago' => $p->medio_pago,
                'monto_recibido' => (float) $p->monto_recibido,
                'monto_aplicado' => (float) $p->monto_aplicado,
                'diferencia' => (float) $p->diferencia,
                'clasificacion' => $p->clasificacion_diferencia,
                'referencia' => $p->referencia,
                'banco' => $p->banco,
                'registrado_por' => $p->registrador?->name,
            ])->withQueryString(),
            'filtros' => ['q' => $q, 'medio' => $medio, 'desde' => $desde, 'hasta' => $hasta],
            'totales' => [
                'aplicado' => $totalAplicado,
                'recibido' => $totalRecibido,
                'count' => $paginado->total(),
            ],
            'mediosPago' => PagoVenta::distinct()->orderBy('medio_pago')->pluck('medio_pago')->filter()->values()->all(),
        ]);
    }
}
