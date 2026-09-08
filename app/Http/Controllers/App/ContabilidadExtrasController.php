<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Cartera\Models\MovimientoContable;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FIL-D · Contabilidad completa Vue:
 *  - Panel contable (KPIs periodo, top cuentas, comparativa)
 *  - Reporte detalle por asiento
 *  - Landing 9 reportes contables
 */
class ContabilidadExtrasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Contador', 'Gerente'])), 403);
            return $next($r);
        })];
    }

    public function panel(Request $request): Response
    {
        $desde = $request->input('desde', now('America/Bogota')->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now('America/Bogota')->endOfMonth()->toDateString());

        $q = MovimientoContable::whereBetween('fecha', [$desde, $hasta]);
        $totalDebe = (float) (clone $q)->sum('debe');
        $totalHaber = (float) (clone $q)->sum('haber');

        // Top 10 cuentas por movimiento
        $topCuentas = MovimientoContable::whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('cuenta_puc, SUM(debe) as debe, SUM(haber) as haber, COUNT(*) as movs')
            ->groupBy('cuenta_puc')->orderByRaw('SUM(debe) + SUM(haber) DESC')->limit(10)->get();

        // Por origen
        $porOrigen = MovimientoContable::whereBetween('fecha', [$desde, $hasta])
            ->whereNotNull('origen_type')
            ->selectRaw('origen_type, COUNT(DISTINCT origen_id) as docs, SUM(debe) as total')
            ->groupBy('origen_type')->orderByDesc('total')->get();

        return Inertia::render('Contabilidad/Panel', [
            'periodo' => ['desde' => $desde, 'hasta' => $hasta],
            'kpis' => [
                'total_debe' => $totalDebe,
                'total_haber' => $totalHaber,
                'balance' => round($totalDebe - $totalHaber, 2),
                'unbalanced' => abs($totalDebe - $totalHaber) > 0.01,
                'movimientos' => (int) $q->count(),
            ],
            'topCuentas' => $topCuentas->map(fn ($r) => [
                'cuenta' => $r->cuenta_puc,
                'debe' => (float) $r->debe, 'haber' => (float) $r->haber,
                'movs' => (int) $r->movs,
            ]),
            'porOrigen' => $porOrigen->map(fn ($r) => [
                'origen' => class_basename($r->origen_type),
                'docs' => (int) $r->docs, 'total' => (float) $r->total,
            ]),
        ]);
    }

    public function reporteDetalle(Request $request): Response
    {
        $tipo = (string) $request->input('tipo', 'factura');
        $id = (int) $request->input('id', 0);

        $tipoClass = [
            'factura' => \App\Modules\Cartera\Models\FacturaVenta::class,
            'pago' => \App\Modules\Cartera\Models\PagoVenta::class,
        ][$tipo] ?? null;

        $movs = [];
        if ($tipoClass && $id) {
            $movs = MovimientoContable::where('origen_type', $tipoClass)->where('origen_id', $id)
                ->orderBy('id')->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'cuenta' => $m->cuenta_puc,
                    'debe' => (float) $m->debe, 'haber' => (float) $m->haber,
                    'descripcion' => $m->descripcion,
                    'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : $m->fecha,
                ])->all();
        }

        $totalD = collect($movs)->sum('debe');
        $totalH = collect($movs)->sum('haber');

        return Inertia::render('Contabilidad/ReporteDetalle', [
            'tipo' => $tipo, 'id' => $id,
            'movimientos' => $movs,
            'totales' => ['debe' => $totalD, 'haber' => $totalH, 'diff' => round($totalD - $totalH, 2)],
        ]);
    }

    public function reportes(): Response
    {
        // Landing con 9 reportes contables
        return Inertia::render('Contabilidad/Reportes', [
            'reportes' => [
                ['nombre' => 'Balance general', 'desc' => 'Activo, pasivo y patrimonio a una fecha', 'href' => '/app/contabilidad'],
                ['nombre' => 'Estado de resultados', 'desc' => 'P&G del periodo', 'href' => '/app/contabilidad/panel'],
                ['nombre' => 'Movimientos por cuenta', 'desc' => 'Detalle por PUC', 'href' => '/app/cartera/movimientos'],
                ['nombre' => 'Libro diario', 'desc' => 'Todos los asientos cronológicos', 'href' => '/app/cartera/movimientos'],
                ['nombre' => 'Balance de comprobación', 'desc' => 'Sumas y saldos por cuenta', 'href' => '/app/contabilidad'],
                ['nombre' => 'Facturas emitidas', 'desc' => 'Ventas del periodo', 'href' => '/app/facturas'],
                ['nombre' => 'Pagos recibidos', 'desc' => 'Ingresos del periodo', 'href' => '/app/pagos'],
                ['nombre' => 'Compras del periodo', 'desc' => 'OCs recibidas', 'href' => '/app/compras/reporte'],
                ['nombre' => 'Retenciones (RETEFTE + RETEICA + RETEIVA)', 'desc' => 'Base para declaración', 'href' => '/app/cartera/movimientos?cuenta=2365'],
            ],
        ]);
    }
}
