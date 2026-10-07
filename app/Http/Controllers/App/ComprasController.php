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
                abort_unless($r->user()?->esContable(), 403);  // A1 FIX · sidebar expone a Contador
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
            ->orderByDesc('fecha_emision')->limit(50)
            ->get()
            ->map(function ($o) {
                $estadoVal = $o->estado?->value;
                // Sprint 3 · A.1 · exponer permisos por fila para que la UI
                // muestre/oculte acciones Editar/Anular/Duplicar/Recibir.
                $editable = in_array($estadoVal, ['borrador', 'pendiente'], true);
                $anulable = ! in_array($estadoVal, ['anulada', 'cerrada'], true);
                $recibible = in_array($estadoVal, ['aprobada', 'parcial', 'en_transito'], true);
                return [
                    'id' => $o->id,
                    'numero' => $o->numero,
                    'proveedor' => $o->proveedor?->nombre_completo,
                    'bodega' => $o->bodega?->nombre,
                    'fecha_emision' => $o->fecha_emision?->toDateString(),
                    'fecha_esperada' => $o->fecha_esperada?->toDateString(),
                    'total' => (float) $o->total,
                    'estado' => $estadoVal,
                    'estado_label' => method_exists($o->estado, 'label') ? $o->estado->label() : $estadoVal,
                    'estado_color' => method_exists($o->estado, 'color') ? $o->estado->color() : 'gray',
                    'puede_editar' => $editable,
                    'puede_anular' => $anulable,
                    'puede_duplicar' => true,
                    'puede_recibir' => $recibible,
                ];
            })->all();
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
            ->orderByDesc('fecha_recepcion')->limit(50)
            ->get()
            ->map(function ($r) {
                // Sprint 3 · A.1 D.1 · badge SIIGO por recepción (F9).
                //   🟢 verde: sincronizado <24h · 🟡 amarillo: >24h · 🔴 rojo: fallido · ⚪ gris: pendiente
                $sync = $r->siigo_sync_at;
                $syncColor = ! $r->siigo_id
                    ? ($r->estado === 'confirmada' ? 'amber' : 'gray')
                    : ($sync?->diffInHours(now()) < 24 ? 'emerald' : 'amber');
                return [
                    'id' => $r->id,
                    'orden_id' => $r->orden?->id,
                    'orden_numero' => $r->orden?->numero,
                    'fecha_recepcion' => $r->fecha_recepcion?->toDateString(),
                    'estado' => $r->estado ?? 'recibida',
                    'observaciones' => $r->observaciones,
                    'receptor' => $r->receptor?->name ?? '—',
                    'siigo_id' => $r->siigo_id,
                    'siigo_number' => $r->siigo_number,
                    'siigo_sync_hace' => $sync?->diffForHumans(),
                    'siigo_color' => $syncColor,
                    'puede_reenviar_siigo' => $r->estado === 'confirmada',
                ];
            })->all();
    }
}
