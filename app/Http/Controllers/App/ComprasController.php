<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Enums\EstadoImportacion;
use App\Modules\Compras\Models\Importacion;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\RecepcionCompra;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ComprasController extends Controller implements HasMiddleware
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
        $tab = (string) $request->input('tab', 'ordenes');

        return Inertia::render('Compras/Index', [
            'tab' => $tab,
            'kpis' => $this->kpis(),
            'ordenes' => $this->ordenes(),
            'importaciones' => $this->importaciones(),
            'recepciones' => $this->recepciones(),
        ]);
    }

    private function kpis(): array
    {
        return [
            'oc_abiertas' => OrdenCompra::whereNotIn('estado', [EstadoOrdenCompra::Cerrada, EstadoOrdenCompra::Anulada])->count(),
            'oc_valor_pendiente' => (float) OrdenCompra::whereNotIn('estado', [EstadoOrdenCompra::Cerrada, EstadoOrdenCompra::Anulada])->sum('total'),
            'contenedores_en_ruta' => Importacion::whereIn('estado', [EstadoImportacion::EnTransito, EstadoImportacion::EnPuerto])->count(),
            'recepciones_mes' => RecepcionCompra::whereBetween('fecha_recepcion', [now('America/Bogota')->startOfMonth(), now('America/Bogota')->endOfDay()])->count(),
        ];
    }

    private function ordenes(): array
    {
        return OrdenCompra::query()
            ->with(['proveedor:id,nombre_completo', 'bodega:id,nombre'])
            ->orderByDesc('fecha_emision')->limit(30)
            ->get()
            ->map(fn ($o) => [
                'id' => $o->id,
                'numero' => $o->numero,
                'proveedor' => $o->proveedor?->nombre_completo,
                'bodega' => $o->bodega?->nombre,
                'fecha_emision' => $o->fecha_emision?->toDateString(),
                'fecha_esperada' => $o->fecha_esperada?->toDateString(),
                'total' => (float) $o->total,
                'estado' => $o->estado?->value,
                'estado_label' => method_exists($o->estado, 'label') ? $o->estado->label() : $o->estado?->value,
                'estado_color' => method_exists($o->estado, 'color') ? $o->estado->color() : 'gray',
            ])->all();
    }

    private function importaciones(): array
    {
        return Importacion::query()
            ->orderByDesc('fecha_zarpe')->limit(30)
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'numero' => $i->numero,
                'contenedor' => $i->contenedor,
                'proveedor_pais' => $i->proveedor_pais,
                'puerto_destino' => $i->puerto_destino,
                'zarpe' => $i->fecha_zarpe?->toDateString(),
                'eta' => $i->eta?->toDateString(),
                'llegada' => $i->fecha_llegada?->toDateString(),
                'liquidacion' => $i->fecha_liquidacion?->toDateString(),
                'valor_total_costo' => (float) $i->valor_total_costo,
                'estado' => $i->estado?->value,
                'estado_label' => method_exists($i->estado, 'label') ? $i->estado->label() : $i->estado?->value,
                'estado_color' => method_exists($i->estado, 'color') ? $i->estado->color() : 'gray',
            ])->all();
    }

    private function recepciones(): array
    {
        return RecepcionCompra::query()
            ->with(['orden:id,numero', 'receptor:id,name'])
            ->orderByDesc('fecha_recepcion')->limit(30)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'orden_id' => $r->orden?->id,
                'orden_numero' => $r->orden?->numero,
                'fecha_recepcion' => $r->fecha_recepcion?->toDateString(),
                'estado' => $r->estado ?? 'recibida',
                'observaciones' => $r->observaciones,
                'receptor' => $r->receptor?->name ?? '—',
            ])->all();
    }
}
