<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\Traslado;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InventarioGestionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            $u = $r->user();
            abort_unless($u && ($u->esAracely() || (method_exists($u, 'esAlistador') && $u->esAlistador())), 403);
            return $next($r);
        })];
    }

    // ---------- KARDEX POR VARIANTE ----------
    public function kardex(Request $request): Response
    {
        $codigo = trim((string) $request->input('codigo', ''));
        $variante = null; $movimientos = []; $saldoTotal = 0;

        if ($codigo) {
            $variante = ProductoVariante::with('producto:id,referencia,nombre')
                ->where('codigo_barras', $codigo)
                ->orWhereHas('producto', fn ($p) => $p->where('referencia', $codigo))
                ->first();
            if ($variante) {
                $movs = InventarioMovimiento::where('variante_id', $variante->id)
                    ->with('ubicacion:id,codigo,nombre')
                    ->orderBy('created_at')->limit(500)->get();
                $saldo = 0;
                $movimientos = $movs->map(function ($m) use (&$saldo) {
                    $saldo += (int) $m->cantidad;
                    return [
                        'id' => $m->id,
                        'fecha' => $m->created_at?->format('Y-m-d H:i'),
                        'tipo' => $m->tipo,
                        'ubicacion' => $m->ubicacion?->codigo . ' · ' . $m->ubicacion?->nombre,
                        'cantidad' => (int) $m->cantidad,
                        'saldo' => $saldo,
                        'referencia' => $m->referencia_tipo ? class_basename($m->referencia_tipo) . '#' . $m->referencia_id : '—',
                        'notas' => $m->notas,
                    ];
                });
                $saldoTotal = $saldo;
            }
        }

        return Inertia::render('Inventario/Kardex', [
            'codigo' => $codigo,
            'variante' => $variante ? [
                'id' => $variante->id, 'codigo' => $variante->codigo_barras,
                'producto' => $variante->producto?->nombre, 'referencia' => $variante->producto?->referencia,
                'detalle' => trim(($variante->color_nombre ?? '') . ' ' . ($variante->talla ?? '')),
            ] : null,
            'movimientos' => $movimientos,
            'saldoTotal' => $saldoTotal,
        ]);
    }

    // ---------- REPORTE STOCK POR BODEGA/UBICACIÓN ----------
    public function reporteStock(): Response
    {
        // Total unidades por ubicación (activa)
        $saldos = InventarioMovimiento::selectRaw('ubicacion_id, SUM(cantidad) as total, COUNT(DISTINCT variante_id) as skus')
            ->groupBy('ubicacion_id')
            ->having('total', '!=', 0)
            ->get()->keyBy('ubicacion_id');

        $ubicaciones = InventarioUbicacion::where('activa', true)->orderBy('codigo')->get()
            ->map(function ($u) use ($saldos) {
                $r = $saldos->get($u->id);
                return [
                    'codigo' => $u->codigo, 'nombre' => $u->nombre,
                    'categoria' => is_object($u->categoria) ? $u->categoria->value : $u->categoria,
                    'skus' => (int) ($r->skus ?? 0),
                    'unidades' => (int) ($r->total ?? 0),
                ];
            });

        // Top 30 variantes con más stock
        $topStock = InventarioMovimiento::selectRaw('variante_id, SUM(cantidad) as total')
            ->groupBy('variante_id')->having('total', '>', 0)->orderByDesc('total')->limit(30)->get();

        $ids = $topStock->pluck('variante_id')->all();
        $vars = ProductoVariante::with('producto:id,referencia,nombre')->whereIn('id', $ids)->get()->keyBy('id');

        $top = $topStock->map(fn ($r) => [
            'codigo' => $vars[$r->variante_id]?->codigo_barras ?? '—',
            'producto' => $vars[$r->variante_id]?->producto?->nombre ?? '—',
            'total' => (int) $r->total,
        ]);

        return Inertia::render('Inventario/ReporteStock', [
            'ubicaciones' => $ubicaciones->values(),
            'topStock' => $top,
            'kpis' => [
                'ubicaciones_activas' => $ubicaciones->count(),
                'unidades_totales' => (int) $ubicaciones->sum('unidades'),
                'skus_con_stock' => $topStock->count(),
            ],
        ]);
    }

    // ---------- CONTEO FÍSICO ----------
    public function conteosIndex(): Response
    {
        $tomas = TomaFisica::with(['ubicacion:id,codigo,nombre', 'creador:id,name'])
            ->orderByDesc('id')->paginate(30);
        return Inertia::render('Inventario/Conteo/Index', [
            'tomas' => $tomas->through(fn ($t) => [
                'id' => $t->id, 'numero' => $t->numero,
                'ubicacion' => $t->ubicacion?->codigo . ' · ' . $t->ubicacion?->nombre,
                'creador' => $t->creador?->name,
                'estado' => $t->estado, 'tipo' => $t->tipo, 'alcance' => $t->alcance,
                'fecha' => $t->fecha_conteo?->format('Y-m-d'),
                'items_diferentes' => (int) $t->items_diferentes,
            ]),
        ]);
    }

    public function conteoCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'ubicacion_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
            'tipo' => ['required', 'in:ciclico,total,puntual'],
            'alcance' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        $numero = 'TF-' . now()->format('ymd') . '-' . str_pad((string) (TomaFisica::whereDate('created_at', today())->count() + 1), 3, '0', STR_PAD_LEFT);
        TomaFisica::create([...$data, 'numero' => $numero, 'creada_por' => auth()->id(), 'fecha_conteo' => now()->toDateString()]);
        return back()->with('success', "Toma {$numero} creada.");
    }

    // ---------- TRASLADOS ----------
    public function trasladosIndex(): Response
    {
        $trasl = Traslado::with(['origen:id,codigo,nombre', 'destino:id,codigo,nombre', 'solicitante:id,name'])
            ->orderByDesc('id')->paginate(30);
        return Inertia::render('Inventario/Traslado/Index', [
            'traslados' => $trasl->through(fn ($t) => [
                'id' => $t->id, 'numero' => $t->numero,
                'origen' => $t->origen?->codigo, 'destino' => $t->destino?->codigo,
                'solicitante' => $t->solicitante?->name,
                'estado' => $t->estado, 'motivo' => $t->motivo,
                'fecha' => $t->fecha_solicitud?->toDateString(),
            ]),
        ]);
    }

    public function trasladoCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'origen_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
            'destino_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id', 'different:origen_id'],
            'motivo' => ['required', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);
        $numero = 'TR-' . now()->format('ymd') . '-' . str_pad((string) (Traslado::whereDate('created_at', today())->count() + 1), 3, '0', STR_PAD_LEFT);
        Traslado::create([...$data, 'numero' => $numero, 'solicitado_por' => auth()->id(), 'fecha_solicitud' => now()->toDateString()]);
        return back()->with('success', "Traslado {$numero} creado.");
    }

    // ---------- ALERTAS STOCK ----------
    public function alertasIndex(): Response
    {
        $configs = AlertaStockConfig::with(['variante.producto:id,nombre', 'ubicacion:id,codigo'])
            ->orderByDesc('id')->paginate(30);
        return Inertia::render('Inventario/Alertas/Index', [
            'alertas' => $configs->through(fn ($a) => [
                'id' => $a->id,
                'producto' => $a->variante?->producto?->nombre,
                'sku' => $a->variante?->codigo_barras,
                'ubicacion' => $a->ubicacion?->codigo,
                'minimo' => (int) $a->stock_minimo,
                'reorden' => (int) $a->punto_reorden,
                'cantidad_reorden' => (int) $a->cantidad_reorden,
                'activa' => (bool) $a->activa,
            ]),
        ]);
    }
}
