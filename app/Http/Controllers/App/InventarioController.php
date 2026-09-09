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

        $rows = DB::table('inventario_movimientos as m')
            ->join('producto_variantes as v', 'v.id', '=', 'm.variante_id')
            ->join('productos as p', 'p.id', '=', 'v.producto_id')
            ->join('inventario_ubicaciones as u', 'u.id', '=', 'm.ubicacion_id')
            ->selectRaw('p.referencia, p.nombre, v.color_codigo, v.talla, v.codigo_barras, u.nombre as bodega, SUM(m.cantidad) as stock')
            ->groupBy('p.referencia', 'p.nombre', 'v.color_codigo', 'v.talla', 'v.codigo_barras', 'u.nombre')
            ->havingRaw('SUM(m.cantidad) > 0')
            ->orderByDesc('stock')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($r) => [
            'referencia' => $r->referencia,
            'nombre' => $r->nombre,
            'color' => $r->color_codigo,
            'talla' => $r->talla,
            'codigo' => $r->codigo_barras,
            'bodega' => $r->bodega,
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
            ->with(['variante.producto', 'ubicacion:id,nombre', 'config'])
            ->where('resuelta', false)
            ->orderByDesc('created_at')->limit(30)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'producto' => $a->variante?->producto?->nombre,
                'referencia' => $a->variante?->producto?->referencia,
                'talla' => $a->variante?->talla,
                'bodega' => $a->ubicacion?->nombre,
                'tipo' => $a->tipo,
                'stock_actual' => (int) $a->saldo_al_disparar,
                // Re-audit M3 PATRÓN ζ (FUNC-A6 / DATOS-C1) · bug: la columna
                // real es `stock_minimo`, no `umbral_minimo`. Antes el KPI
                // siempre mostraba 0. Se agrega también `stock_maximo` +
                // `punto_reorden` según el tipo para el widget.
                'umbral' => (int) ($a->config?->stock_minimo ?? 0),
                'punto_reorden' => (int) ($a->config?->punto_reorden ?? 0),
                'stock_maximo' => (int) ($a->config?->stock_maximo ?? 0),
                'creada' => $a->created_at?->diffForHumans(),
            ])->all();
    }
}
