<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Actions\CrearOrdenCompra;
use App\Modules\Compras\Actions\AprobarOrdenCompra;
use App\Modules\Compras\Actions\RecibirMercancia;
use App\Modules\Compras\Actions\LiquidarImportacion;
use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Enums\EstadoImportacion;
use App\Modules\Compras\Models\GastoImportacion;
use App\Modules\Compras\Models\Importacion;
use App\Modules\Compras\Models\OrdenCompra;
use App\Modules\Compras\Models\RecepcionCompra;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FIL-A · CRUD detalle Compras (paridad Filament):
 *  - Orden de compra: crear, editar items, aprobar, anular
 *  - Recepción: preparar desde OC, confirmar
 *  - Importación: crear, agregar gastos, liquidar (prorratear costos)
 *  - Reporte compras
 */
class ComprasGestionController extends Controller implements HasMiddleware
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

    // ================== ORDEN DE COMPRA ==================
    public function ocShow(int $orden): Response
    {
        $o = OrdenCompra::with(['proveedor:id,nombre_completo,razon_social', 'items'])->findOrFail($orden);
        return Inertia::render('Compras/OC/Show', [
            'orden' => [
                'id' => $o->id,
                'numero' => $o->numero,
                'proveedor' => $o->proveedor?->razon_social ?: $o->proveedor?->nombre_completo,
                'proveedor_id' => $o->proveedor_id,
                'estado' => is_object($o->estado) ? $o->estado->value : $o->estado,
                'tipo' => $o->tipo,
                'moneda' => $o->moneda,
                'tasa_cambio' => (float) $o->tasa_cambio,
                'fecha_emision' => $o->fecha_emision?->toDateString(),
                'fecha_esperada' => $o->fecha_esperada?->toDateString(),
                'subtotal' => (float) $o->subtotal,
                'iva' => (float) $o->iva,
                'total' => (float) $o->total,
                'descuento' => (float) $o->descuento,
                'observaciones' => $o->observaciones,
                'items' => $o->items->map(fn ($i) => [
                    'id' => $i->id, 'descripcion' => $i->descripcion,
                    'cantidad' => (float) $i->cantidad, 'cantidad_recibida' => (float) $i->cantidad_recibida,
                    'precio_unit' => (float) $i->precio_unit, 'iva_pct' => (float) $i->iva_pct,
                    'descuento_pct' => (float) $i->descuento_pct,
                    'subtotal' => (float) $i->subtotal, 'total' => (float) $i->total,
                ])->all(),
            ],
        ]);
    }

    public function ocForm(): Response
    {
        return Inertia::render('Compras/OC/Nueva', [
            'proveedores' => \App\Models\Contacto::where('es_proveedor', true)->where('activo', true)
                ->orderBy('nombre_completo')->limit(200)->get(['id', 'nombre_completo', 'razon_social'])
                ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->razon_social ?: $c->nombre_completo]),
        ]);
    }

    public function ocCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'proveedor_id' => ['required', 'integer', 'exists:contactos,id'],
            'tipo' => ['required', 'string', 'max:30'],
            'moneda' => ['required', 'string', 'size:3'],
            'tasa_cambio' => ['required', 'numeric', 'min:0'],
            'fecha_esperada' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.descripcion' => ['required', 'string', 'max:200'],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.001'],
            'items.*.precio_unit' => ['required', 'numeric', 'min:0'],
            'items.*.iva_pct' => ['nullable', 'numeric'],
            'items.*.descuento_pct' => ['nullable', 'numeric'],
            'items.*.producto_id' => ['nullable', 'integer'],
            'items.*.variante_id' => ['nullable', 'integer'],
        ]);
        $oc = CrearOrdenCompra::run($data);
        return redirect()->route('app.compras.oc.show', $oc->id)->with('success', "OC {$oc->numero} creada.");
    }

    public function ocAprobar(int $orden): RedirectResponse
    {
        $o = OrdenCompra::findOrFail($orden);
        AprobarOrdenCompra::run($o);
        return back()->with('success', "OC {$o->numero} aprobada.");
    }

    public function ocAnular(Request $r, int $orden): RedirectResponse
    {
        $data = $r->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']]);
        $o = OrdenCompra::findOrFail($orden);
        abort_if(in_array($o->estado, [EstadoOrdenCompra::Recibida, EstadoOrdenCompra::Cerrada]), 422, 'OC ya recibida/cerrada.');
        $o->update(['estado' => EstadoOrdenCompra::Anulada, 'observaciones' => ($o->observaciones . "\n[ANULADA] " . $data['motivo'])]);
        return back()->with('success', "OC {$o->numero} anulada.");
    }

    // ================== RECEPCIÓN ==================
    public function recepcionShow(int $recepcion): Response
    {
        $rc = RecepcionCompra::with(['orden:id,numero', 'items'])->findOrFail($recepcion);
        return Inertia::render('Compras/Recepcion/Show', [
            'recepcion' => [
                'id' => $rc->id, 'numero' => $rc->numero,
                'orden_numero' => $rc->orden?->numero,
                'estado' => $rc->estado,
                'fecha_recepcion' => $rc->fecha_recepcion?->format('Y-m-d H:i'),
                'remision_proveedor' => $rc->remision_proveedor,
                'factura_proveedor' => $rc->factura_proveedor,
                'transportista' => $rc->transportista,
                'observaciones' => $rc->observaciones,
                'total_recibido' => (float) $rc->total_recibido,
                'items' => $rc->items->map(fn ($i) => [
                    'id' => $i->id,
                    'cantidad_recibida' => (float) $i->cantidad_recibida,
                    'costo_unit' => (float) $i->costo_unit,
                    'subtotal' => (float) $i->subtotal,
                    'lote' => $i->lote,
                    'observaciones' => $i->observaciones,
                ])->all(),
            ],
        ]);
    }

    public function recepcionDesdeOc(int $orden): RedirectResponse
    {
        // Redirige a form-preparar-recepción con items pre-llenados desde OC.
        return redirect()->route('app.compras.recepcion.nueva', ['oc' => $orden]);
    }

    public function recepcionForm(Request $request): Response
    {
        $ocId = (int) $request->input('oc', 0);
        $oc = $ocId ? OrdenCompra::with(['items', 'proveedor:id,nombre_completo,razon_social'])->find($ocId) : null;
        return Inertia::render('Compras/Recepcion/Nueva', [
            'oc' => $oc ? [
                'id' => $oc->id, 'numero' => $oc->numero,
                'proveedor' => $oc->proveedor?->razon_social ?: $oc->proveedor?->nombre_completo,
                'items' => $oc->items->map(fn ($i) => [
                    'id' => $i->id, 'descripcion' => $i->descripcion,
                    'cantidad_pendiente' => (float) $i->cantidad - (float) $i->cantidad_recibida,
                    'precio_unit' => (float) $i->precio_unit,
                ])->filter(fn ($x) => $x['cantidad_pendiente'] > 0)->values(),
            ] : null,
        ]);
    }

    public function recepcionCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'orden_id' => ['required', 'integer', 'exists:compras_ordenes,id'],
            'remision_proveedor' => ['nullable', 'string', 'max:100'],
            'factura_proveedor' => ['nullable', 'string', 'max:100'],
            'transportista' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.orden_item_id' => ['required', 'integer'],
            'items.*.cantidad_recibida' => ['required', 'numeric', 'min:0.001'],
            'items.*.lote' => ['nullable', 'string', 'max:80'],
        ]);
        $rc = RecibirMercancia::run($data);
        return redirect()->route('app.compras.recepcion.show', $rc->id)->with('success', "Recepción {$rc->numero} creada.");
    }

    // ================== IMPORTACIÓN ==================
    public function importacionShow(int $importacion): Response
    {
        $imp = Importacion::with(['lineas', 'gastos'])->findOrFail($importacion);
        return Inertia::render('Compras/Importacion/Show', [
            'importacion' => [
                'id' => $imp->id, 'numero' => $imp->numero,
                'contenedor' => $imp->contenedor, 'bl_awb' => $imp->bl_awb,
                'estado' => is_object($imp->estado) ? $imp->estado->value : $imp->estado,
                'puerto_origen' => $imp->puerto_origen, 'puerto_destino' => $imp->puerto_destino,
                'incoterm' => $imp->incoterm, 'moneda_origen' => $imp->moneda_origen,
                'tasa_cambio_liquidacion' => (float) $imp->tasa_cambio_liquidacion,
                'fecha_zarpe' => $imp->fecha_zarpe?->toDateString(),
                'eta' => $imp->eta?->toDateString(),
                'fecha_llegada' => $imp->fecha_llegada?->toDateString(),
                'fecha_liquidacion' => $imp->fecha_liquidacion?->toDateString(),
                'observaciones' => $imp->observaciones,
                'lineas' => $imp->lineas->map(fn ($l) => [
                    'id' => $l->id,
                    'cantidad' => (float) $l->cantidad,
                    'costo_fob_unit' => (float) $l->costo_fob_unit,
                    'costo_fob_total' => (float) $l->costo_fob_total,
                    'gasto_prorrateado' => (float) $l->gasto_prorrateado,
                    'costo_final_unit' => (float) $l->costo_final_unit,
                ])->all(),
                'gastos' => $imp->gastos->map(fn ($g) => [
                    'id' => $g->id, 'concepto' => $g->concepto,
                    'moneda' => $g->moneda, 'monto' => (float) $g->monto,
                    'monto_base' => (float) $g->monto_base,
                    'capitalizable' => (bool) $g->capitalizable,
                    'metodo_prorrateo' => $g->metodo_prorrateo,
                    'fecha' => $g->fecha?->toDateString(),
                ])->all(),
                'total_gastos' => (float) $imp->gastos->sum('monto_base'),
                'total_fob' => (float) $imp->lineas->sum('costo_fob_total'),
            ],
        ]);
    }

    public function importacionCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'contenedor' => ['nullable', 'string', 'max:50'],
            'bl_awb' => ['nullable', 'string', 'max:50'],
            'proveedor_pais' => ['nullable', 'string', 'max:100'],
            'puerto_origen' => ['nullable', 'string', 'max:100'],
            'puerto_destino' => ['nullable', 'string', 'max:100'],
            'incoterm' => ['nullable', 'string', 'max:10'],
            'moneda_origen' => ['required', 'string', 'size:3'],
            'tasa_cambio_liquidacion' => ['nullable', 'numeric', 'min:0'],
            'fecha_zarpe' => ['nullable', 'date'],
            'eta' => ['nullable', 'date'],
        ]);
        // Consecutivo simple
        $numero = 'IMP-' . now()->format('Ym') . '-' . str_pad((string) (Importacion::whereYear('created_at', now()->year)->count() + 1), 4, '0', STR_PAD_LEFT);
        $imp = Importacion::create([...$data, 'numero' => $numero, 'creado_por' => auth()->id()]);
        return redirect()->route('app.compras.importacion.show', $imp->id)->with('success', "Importación {$imp->numero} creada.");
    }

    public function importacionGastoAgregar(Request $r, int $importacion): RedirectResponse
    {
        $data = $r->validate([
            'concepto' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'moneda' => ['required', 'string', 'size:3'],
            'monto' => ['required', 'numeric', 'min:0'],
            'capitalizable' => ['boolean'],
            'metodo_prorrateo' => ['required', 'string', 'in:cantidad,fob,volumen'],
            'factura_proveedor' => ['nullable', 'string', 'max:100'],
            'fecha' => ['required', 'date'],
            'proveedor_id' => ['nullable', 'integer'],
        ]);
        $imp = Importacion::findOrFail($importacion);
        $data['monto_base'] = $data['monto'] * (float) ($imp->tasa_cambio_liquidacion ?: 1);
        $imp->gastos()->create($data);
        return back()->with('success', 'Gasto agregado.');
    }

    public function importacionLiquidar(int $importacion): RedirectResponse
    {
        $imp = Importacion::findOrFail($importacion);
        abort_if($imp->estado === EstadoImportacion::Liquidada, 422, 'Ya liquidada.');
        LiquidarImportacion::run($imp);
        return back()->with('success', 'Importación liquidada — costos prorrateados.');
    }

    // ================== REPORTE COMPRAS ==================
    public function reporte(): Response
    {
        $inicio = now('America/Bogota')->startOfMonth();
        $fin = now('America/Bogota')->endOfMonth();
        $ocPendientes = OrdenCompra::whereIn('estado', [EstadoOrdenCompra::Aprobada, EstadoOrdenCompra::Enviada, EstadoOrdenCompra::Parcial])->count();
        $ocEsteMes = OrdenCompra::whereBetween('fecha_emision', [$inicio, $fin])->sum('total');
        $contenedoresPorLiquidar = Importacion::whereIn('estado', [EstadoImportacion::Nacionalizada, EstadoImportacion::EnPuerto])->count();
        $totalFobMes = Importacion::whereBetween('created_at', [$inicio, $fin])
            ->with('lineas')->get()->sum(fn ($i) => $i->lineas->sum('costo_fob_total'));

        $topProveedores = OrdenCompra::selectRaw('proveedor_id, COUNT(*) as ocs, SUM(total) as total')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->groupBy('proveedor_id')->orderByDesc('total')->limit(10)
            ->with('proveedor:id,nombre_completo,razon_social')
            ->get()->map(fn ($r) => [
                'nombre' => $r->proveedor?->razon_social ?: $r->proveedor?->nombre_completo,
                'ocs' => (int) $r->ocs, 'total' => (float) $r->total,
            ])->all();

        return Inertia::render('Compras/Reporte', [
            'periodo' => ['inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString()],
            'kpis' => [
                'oc_pendientes' => $ocPendientes,
                'oc_este_mes' => (float) $ocEsteMes,
                'contenedores_por_liquidar' => $contenedoresPorLiquidar,
                'total_fob_mes' => (float) $totalFobMes,
            ],
            'topProveedores' => $topProveedores,
        ]);
    }
}
