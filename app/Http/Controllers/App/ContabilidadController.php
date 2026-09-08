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

class ContabilidadController extends Controller implements HasMiddleware
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
        $desde = $request->input('desde', now('America/Bogota')->startOfMonth()->toDateString());
        $hasta = $request->input('hasta', now('America/Bogota')->toDateString());

        return Inertia::render('Contabilidad/Index', [
            'filtros' => ['desde' => $desde, 'hasta' => $hasta],
            'kpis' => $this->kpis($desde, $hasta),
            'porCuenta' => $this->balanceComprobacion($desde, $hasta),
            'movimientosRecientes' => $this->movimientosRecientes($desde, $hasta),
        ]);
    }

    private function kpis(string $desde, string $hasta): array
    {
        $q = MovimientoContable::query()->whereBetween('fecha', [$desde, $hasta]);
        $totalDebe = (float) (clone $q)->sum('debe');
        $totalHaber = (float) (clone $q)->sum('haber');
        $balance = round($totalDebe - $totalHaber, 2);
        return [
            'total_debe' => $totalDebe,
            'total_haber' => $totalHaber,
            'balance' => $balance,
            // QA-D Bloque3: flag UNBALANCED — tolerancia 1 centavo por drift de round().
            'unbalanced' => abs($balance) > 0.01,
            'movimientos' => (int) $q->count(),
        ];
    }

    private function balanceComprobacion(string $desde, string $hasta): array
    {
        return DB::table('movimientos_contables')
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('cuenta_puc, SUM(debe) as debe, SUM(haber) as haber, SUM(debe - haber) as saldo')
            ->groupBy('cuenta_puc')
            ->orderBy('cuenta_puc')
            ->get()
            ->map(fn ($r) => [
                'cuenta_puc' => $r->cuenta_puc,
                'debe' => (float) $r->debe,
                'haber' => (float) $r->haber,
                'saldo' => (float) $r->saldo,
            ])->all();
    }

    private function movimientosRecientes(string $desde, string $hasta, int $limit = 50): array
    {
        return MovimientoContable::query()
            ->with(['user:id,name'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : (string) $m->fecha,
                'cuenta_puc' => $m->cuenta_puc,
                'debe' => (float) $m->debe,
                'haber' => (float) $m->haber,
                'descripcion' => $m->descripcion,
                'origen' => class_basename($m->origen_type ?? ''),
                'usuario' => $m->user?->name,
            ])->all();
    }
}
