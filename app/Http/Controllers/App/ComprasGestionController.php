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
            // Re-audit M2 UX-A4 · bodegas destino para elección explícita en OC.
            'bodegas' => \App\Modules\Dropi\Models\InventarioUbicacion::orderBy('nombre')->get(['id', 'nombre'])
                ->map(fn ($b) => ['id' => $b->id, 'nombre' => $b->nombre]),
        ]);
    }

    public function ocCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'proveedor_id' => ['required', 'integer', 'exists:contactos,id'],
            // Re-audit M2 UX-A4 · bodega ahora obligatoria al crear (antes silenciosa).
            'bodega_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
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
        // Re-audit M2 FUNC-C4 · agregado Anulada (idempotencia — evita re-anular).
        abort_if(in_array($o->estado, [EstadoOrdenCompra::Recibida, EstadoOrdenCompra::Cerrada, EstadoOrdenCompra::Anulada]), 422, 'OC ya recibida/cerrada/anulada.');
        // Re-audit M2 PATRÓN B (DATOS-C2) · asignación DIRECTA. Antes `$o->update(['estado'=>...])`
        // era mass-assign silencioso: `estado` no estaba en fillable y Laravel lo DESCARTABA
        // → la OC quedaba "anulada" solo en observaciones pero seguía viva. Con $guarded=['id']
        // + asignación directa el estado se persiste correcto.
        $o->estado = EstadoOrdenCompra::Anulada;
        $o->anulado_at = now();
        $o->anulado_por = auth()->id();
        $o->motivo_anulacion = $data['motivo'];
        $o->observaciones = trim(($o->observaciones ?? '') . "\n[ANULADA " . now()->toDateString() . " por " . auth()->user()?->name . "] " . $data['motivo']);
        $o->save();
        // Re-audit M2 R3 PATRÓN R (SEG-M2) · usa array_key_exists — antes
        // `config('logging.channels.audit')` returnaba truthy incluso si `[]`.
        \Illuminate\Support\Facades\Log::channel(
            array_key_exists('audit', config('logging.channels') ?? []) ? 'audit' : 'stack'
        )->info('compras.oc.anular', [
            'user_id' => auth()->id(), 'orden_id' => $o->id, 'numero' => $o->numero,
            'total' => (float) $o->total, 'motivo' => $data['motivo'],
        ]);
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
        // Re-audit M2 R3 PATRÓN Q (FUNC-C3) · TX UNIFICADA. Antes había una tx
        //   externa que committeaba `RecepcionCompra` + items, y DESPUÉS otra
        //   tx en `RecibirMercancia::handle`. Si el Action tronaba, quedaba
        //   recepción huérfana en `borrador` + cada retry creaba OTRA con nuevo
        //   consecutivo → acumulación de "recepciones fantasma".
        //   Ahora TODO va bajo el mismo `DB::transaction` — o se persiste el
        //   flujo completo o se rollea todo. `RecibirMercancia` sigue haciendo
        //   su propia tx anidada (Laravel las combina, sin doble commit).
        //
        // Re-audit M2 R3 PATRÓN Q (FUNC-A3) · dedupe `orden_item_id` — antes un
        //   payload con duplicados pasaba items y el Action detectaba
        //   sobre-recepción en el segundo intento, pero la recepción ya estaba
        //   committeada. Ahora fusionamos duplicados sumando cantidades ANTES
        //   de crear la recepción.
        //
        // Re-audit M2 R3 PATRÓN M (FUNC-C1) · fillable clave = `recibido_por`.
        //   Antes pasaba `'creado_por'` → mass-assign silencioso lo descartaba,
        //   la columna quedaba NULL y perdíamos trazabilidad de autor.

        $data = $r->validate([
            'orden_id' => ['required', 'integer', 'exists:compras_ordenes,id'],
            'remision_proveedor' => ['nullable', 'string', 'max:100'],
            'factura_proveedor' => ['nullable', 'string', 'max:100'],
            'transportista' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.orden_item_id' => ['required', 'integer', 'exists:compras_orden_items,id'],
            'items.*.cantidad_recibida' => ['required', 'numeric', 'min:0.001'],
            'items.*.lote' => ['nullable', 'string', 'max:80'],
        ]);

        $orden = OrdenCompra::with('items')->findOrFail($data['orden_id']);
        abort_unless(in_array($orden->estado, [EstadoOrdenCompra::Aprobada, EstadoOrdenCompra::Parcial, EstadoOrdenCompra::Enviada]), 422, 'La OC no está en estado receptable.');
        abort_if($orden->bodega_id === null, 422, 'La OC no tiene bodega destino asignada.');

        $itemsOc = $orden->items->pluck('id')->all();
        foreach ($data['items'] as $it) {
            abort_unless(in_array((int) $it['orden_item_id'], $itemsOc, true), 422, 'Item de recepción no pertenece a esta OC.');
        }

        // Dedupe: si vienen 2 líneas con mismo orden_item_id, fusionamos qty.
        $itemsFusionados = [];
        foreach ($data['items'] as $it) {
            $key = (int) $it['orden_item_id'];
            if (isset($itemsFusionados[$key])) {
                $itemsFusionados[$key]['cantidad_recibida'] += (float) $it['cantidad_recibida'];
                // conservar lote no-vacío si aparece
                $itemsFusionados[$key]['lote'] = $itemsFusionados[$key]['lote'] ?: ($it['lote'] ?? null);
            } else {
                $itemsFusionados[$key] = [
                    'cantidad_recibida' => (float) $it['cantidad_recibida'],
                    'lote' => $it['lote'] ?? null,
                ];
            }
        }

        $rc = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $orden, $itemsFusionados) {
            $rc = RecepcionCompra::create([
                'numero' => RecepcionCompra::siguienteNumero(),
                'orden_id' => $orden->id,
                'bodega_id' => $orden->bodega_id,
                'estado' => 'borrador',
                'fecha_recepcion' => now(),
                'remision_proveedor' => $data['remision_proveedor'] ?? null,
                'factura_proveedor' => $data['factura_proveedor'] ?? null,
                'transportista' => $data['transportista'] ?? null,
                'observaciones' => $data['observaciones'] ?? null,
                // Clave real del $fillable (antes 'creado_por' se descartaba).
                'recibido_por' => auth()->id(),
            ]);
            foreach ($itemsFusionados as $ocItemId => $it) {
                $ocItem = $orden->items->firstWhere('id', $ocItemId);
                $costoUnit = (float) $ocItem->precio_unit;
                $cantidad = (float) $it['cantidad_recibida'];
                $rc->items()->create([
                    'orden_item_id' => $ocItem->id,
                    'variante_id' => $ocItem->variante_id,
                    // 'descripcion' NO existe en RecepcionCompraItem; NO enviar
                    // para evitar la falla silenciosa del mass-assign.
                    'cantidad_recibida' => $cantidad,
                    'costo_unit' => $costoUnit,
                    'subtotal' => round($costoUnit * $cantidad, 2),
                    'lote' => $it['lote'] ?? null,
                ]);
            }

            // Confirmación dentro de la MISMA tx externa. Si esto truena,
            // rollback total incluida la recepción recién creada.
            return RecibirMercancia::run($rc);
        });

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

    /**
     * Re-audit M2 UX-C1 · pantalla nueva importación (antes solo por Filament).
     */
    public function importacionForm(): Response
    {
        return Inertia::render('Compras/Importacion/Nueva');
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
        // Re-audit M2 PATRÓN C + I · usa siguienteNumero() atómico del modelo
        // (con lockForUpdate + año en TZ Colombia). Antes: `count()+1` sin lock =
        // colisión concurrente; `whereYear('created_at')` sin TZ = 31-dic 20:00
        // Bogotá cruzaba al año siguiente en UTC → salto de secuencia.
        $numero = Importacion::siguienteNumero();
        $imp = Importacion::create([...$data, 'numero' => $numero, 'creado_por' => auth()->id()]);
        return redirect()->route('app.compras.importacion.show', $imp->id)->with('success', "Importación {$imp->numero} creada.");
    }

    public function importacionGastoAgregar(Request $r, int $importacion): RedirectResponse
    {
        // Re-audit M2 PATRÓN L (FUNC-A6 / DATOS-A7) · unificado con Action:
        // valor|cantidad|peso|volumen (antes HTTP aceptaba "fob" que caía al
        // default silenciosamente y rechazaba "peso" que Filament sí ofrece).
        $data = $r->validate([
            'concepto' => ['required', 'string', 'max:50'],
            'descripcion' => ['nullable', 'string', 'max:200'],
            'moneda' => ['required', 'string', 'size:3'],
            'monto' => ['required', 'numeric', 'min:0'],
            'capitalizable' => ['boolean'],
            'metodo_prorrateo' => ['required', 'string', 'in:valor,cantidad,peso,volumen'],
            'factura_proveedor' => ['nullable', 'string', 'max:100'],
            'fecha' => ['required', 'date'],
            'proveedor_id' => ['nullable', 'integer'],
        ]);
        $imp = Importacion::findOrFail($importacion);
        abort_if(
            $imp->estado === EstadoImportacion::Liquidada,
            422,
            'La importación ya está liquidada. Requiere reversar liquidación (a definir) para agregar gastos.'
        );
        // Re-audit M2 R3 PATRÓN R (SEG-B2) · si moneda ≠ COP y no hay TRM
        // definida, ABORTA — antes se usaba 1 silenciosamente y USD 1200
        // entraba al asiento como COP 1200 (subvaluación grave del costo).
        if ($data['moneda'] !== 'COP' && ((float) $imp->tasa_cambio_liquidacion) <= 0) {
            abort(422, "Falta definir la tasa de cambio de la importación (moneda={$data['moneda']}). Edítala y agrega TRM antes de agregar gastos en moneda extranjera.");
        }
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
