<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Inventario\Models\AlertaStockDisparada;
use App\Modules\Inventario\Models\ReservaInventario;
use App\Modules\Inventario\Models\TomaFisica;
use App\Modules\Inventario\Models\Traslado;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller implements HasMiddleware
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
        return Inertia::render('Inventario/Index', [
            'tab' => (string) $request->input('tab', 'stock'),
            'kpis' => $this->kpis(),
            'stock' => $this->stockPorBodega(),
            'traslados' => $this->traslados(),
            'tomas' => $this->tomas(),
            'alertas' => $this->alertas(),
        ]);
    }

    private function kpis(): array
    {
        $stockTotal = Schema::hasTable('inventario_movimientos')
            ? (int) DB::table('inventario_movimientos')->sum('cantidad')
            : 0;

        return [
            'unidades_stock' => $stockTotal,
            'alertas_activas' => AlertaStockDisparada::where('resuelta', false)->count(),
            'traslados_transito' => Traslado::where('estado', 'en_transito')->count(),
            'reservas_activas' => ReservaInventario::where('activa', true)->count(),
        ];
    }

    private function stockPorBodega(int $limit = 40): array
    {
        if (! Schema::hasTable('inventario_movimientos')) return [];

        // C-F3 FIX ALTO auditor · el reporte debe INCLUIR productos agregados.
        //   Antes: INNER JOIN a producto_variantes filtraba los agregados.
        //   Ahora: UNION de granulares (variante_id set) + agregados (variante_id NULL).
        $granulares = DB::table('inventario_movimientos as m')
            ->join('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->join('productos as p', 'p.id', '=', 'v.producto_id')
            ->join('inventario_ubicaciones as u', 'u.id', '=', 'm.ubicacion_id')
            ->selectRaw('
                p.referencia,
                p.nombre,
                v.color_codigo as color,
                v.talla,
                v.codigo_barras as codigo,
                u.nombre as bodega,
                "granular" as modo,
                SUM(m.cantidad) as stock
            ')
            ->groupBy('p.referencia', 'p.nombre', 'v.color_codigo', 'v.talla', 'v.codigo_barras', 'u.nombre')
            ->havingRaw('SUM(m.cantidad) > 0');

        $agregados = DB::table('inventario_movimientos as m')
            ->join('productos as p', 'p.id', '=', 'm.producto_id')
            ->join('inventario_ubicaciones as u', 'u.id', '=', 'm.ubicacion_id')
            ->whereNull('m.variante_id')
            ->selectRaw('
                p.referencia,
                p.nombre,
                p.descripcion as color,
                NULL as talla,
                NULL as codigo,
                u.nombre as bodega,
                "agregado" as modo,
                SUM(m.cantidad) as stock
            ')
            ->groupBy('p.referencia', 'p.nombre', 'p.descripcion', 'u.nombre')
            ->havingRaw('SUM(m.cantidad) > 0');

        $rows = $granulares->union($agregados)
            ->orderByDesc('stock')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'referencia' => $r->referencia,
            'nombre' => $r->nombre,
            'color' => $r->color,
            'talla' => $r->talla,
            'codigo' => $r->codigo,
            'bodega' => $r->bodega,
            'modo' => $r->modo,
            'stock' => (int) $r->stock,
        ])->all();
    }

    private function traslados(): array
    {
        return Traslado::query()
            ->with(['origen:id,nombre', 'destino:id,nombre', 'solicitante:id,name'])
            ->orderByDesc('created_at')->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'origen' => $t->origen?->nombre,
                'destino' => $t->destino?->nombre,
                'motivo' => $t->motivo,
                'estado' => $t->estado,
                'fecha_solicitud' => $t->fecha_solicitud?->toDateString(),
                'fecha_ejecucion' => $t->fecha_ejecucion?->toDateString(),
                'creador' => $t->solicitante?->name,
            ])->all();
    }

    private function tomas(): array
    {
        return TomaFisica::query()
            ->with(['ubicacion:id,nombre', 'creador:id,name'])
            ->orderByDesc('created_at')->limit(20)
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'bodega' => $t->ubicacion?->nombre,
                'alcance' => $t->alcance,
                'fecha_conteo' => $t->fecha_conteo?->toDateString(),
                'estado' => $t->estado,
                'creador' => $t->creador?->name,
                'observaciones' => $t->observaciones,
            ])->all();
    }

    private function alertas(): array
    {
        return AlertaStockDisparada::query()
            // C-F-QA3 · eager-load producto agregado
            ->with(['variante.producto', 'producto', 'ubicacion:id,nombre', 'config'])
            ->where('resuelta', false)
            ->orderByDesc('created_at')->limit(30)
            ->get()
            ->map(function ($a) {
                $esAgg = $a->esAgregada();
                return [
                'id' => $a->id,
                // C-F-QA3 · fallback polimórfico: agregado usa producto directo
                'producto' => $esAgg
                    ? (($a->producto?->nombre ?? '—').' · AGREGADO')
                    : $a->variante?->producto?->nombre,
                'referencia' => $esAgg
                    ? $a->producto?->referencia
                    : $a->variante?->producto?->referencia,
                'talla' => $esAgg ? null : $a->variante?->talla,
                'bodega' => $a->ubicacion?->nombre,
                'tipo' => $a->tipo,
                'es_agregado' => $esAgg,
                'stock_actual' => (int) $a->saldo_al_disparar,
                // Re-audit M3 PATRÓN ζ (FUNC-A6 / DATOS-C1) · bug: la columna
                // real es `stock_minimo`, no `umbral_minimo`. Antes el KPI
                // siempre mostraba 0. Se agrega también `stock_maximo` +
                // `punto_reorden` según el tipo para el widget.
                'umbral' => (int) ($a->config?->stock_minimo ?? 0),
                'punto_reorden' => (int) ($a->config?->punto_reorden ?? 0),
                'stock_maximo' => (int) ($a->config?->stock_maximo ?? 0),
                'creada' => $a->created_at?->diffForHumans(),
                ];
            })->all();
    }
}
