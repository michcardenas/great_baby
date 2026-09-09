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
                // Re-audit M3 λ (SEG-M6) · Alistador sólo ve movs de sus bodegas
                //   asignadas. Aracely/Gerencia ve todo.
                $u = $request->user();
                $q = InventarioMovimiento::where('variante_id', $variante->id)
                    ->with('ubicacion:id,codigo,nombre');
                if (! $u->esAracely()) {
                    $bodegas = $u->bodegasAsignadasIds();
                    if (empty($bodegas)) {
                        $q->whereRaw('1=0'); // fail-closed: sin bodegas asignadas → nada
                    } else {
                        $q->whereIn('ubicacion_id', $bodegas);
                    }
                }
                $movs = $q->orderBy('created_at')->limit(500)->get();
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
        // Re-audit M3 PATRÓN η · alcance con regex whitelist coherente con
        //   PrepararTomaFisica (marca:N | categoria:N).
        // Re-audit M3 PATRÓN M19 · valida `alcance` con regex, no string libre.
        $data = $r->validate([
            'ubicacion_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
            'tipo' => ['required', 'in:ciclico,total,puntual'],
            'alcance' => ['nullable', 'string', 'regex:/^(marca|categoria):\d+$/'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        // Re-audit M3 λ (SEG-M2) · Alistador sólo crea toma en bodegas asignadas.
        $u = $r->user();
        if (! $u->esAracely()) {
            $bodegas = $u->bodegasAsignadasIds();
            abort_unless(
                in_array((int) $data['ubicacion_id'], $bodegas, true),
                403,
                'Sólo puedes crear tomas en bodegas asignadas a tu perfil.'
            );
        }

        // Re-audit M3 PATRÓN η · uso `TomaFisica::siguienteNumero()` atómico.
        //   Antes: `count()+1` bajo concurrencia = colisión. Además formato
        //   TF-ymd-NNN divergía del TF-YYYY-NNNNNN del método del modelo.
        $data['numero'] = TomaFisica::siguienteNumero();
        $data['creada_por'] = auth()->id();
        $data['fecha_conteo'] = now('America/Bogota')->toDateString();
        $data['estado'] = \App\Modules\Inventario\Enums\EstadoTomaFisica::Borrador;
        $toma = TomaFisica::create($data);
        return back()->with('success', "Toma {$toma->numero} creada.");
    }

    // ---------- TRASLADOS ----------
    public function trasladosIndex(Request $r): Response
    {
        // PATRÓN α · pasar lista de ubicaciones para el selector por NOMBRE
        //   (antes: modal pedía "Origen (ID)" y "Destino (ID)" → operario
        //   tenía que ir a otro tab a copiar números).
        $ubicaciones = InventarioUbicacion::where('activa', true)
            ->orderBy('codigo')->get(['id','codigo','nombre','categoria']);

        $q = Traslado::with(['origen:id,codigo,nombre', 'destino:id,codigo,nombre', 'solicitante:id,name'])
            ->orderByDesc('id');
        if ($estado = $r->input('estado')) $q->where('estado', $estado);
        if ($origen = $r->input('origen_id')) $q->where('origen_id', $origen);

        $trasl = $q->paginate(30)->withQueryString();

        return Inertia::render('Inventario/Traslado/Index', [
            'traslados' => $trasl->through(fn ($t) => [
                'id' => $t->id, 'numero' => $t->numero,
                'origen' => $t->origen?->codigo . ' · ' . $t->origen?->nombre,
                'destino' => $t->destino?->codigo . ' · ' . $t->destino?->nombre,
                'solicitante' => $t->solicitante?->name,
                'estado' => is_object($t->estado) ? $t->estado->value : $t->estado,
                'motivo' => $t->motivo,
                'fecha' => optional($t->fecha_solicitud)->toDateString(),
            ]),
            'ubicaciones' => $ubicaciones,
            'filtros' => $r->only(['estado','origen_id']),
        ]);
    }

    // ---------- TRASLADO SHOW (repeater items + acciones state machine) ----------
    public function trasladoShow(int $id): Response
    {
        $t = Traslado::with(['items.variante.producto', 'origen', 'destino', 'solicitante', 'ejecutor'])
            ->findOrFail($id);

        return Inertia::render('Inventario/Traslado/Show', [
            'traslado' => [
                'id' => $t->id, 'numero' => $t->numero,
                'origen' => ['id' => $t->origen_id, 'nombre' => $t->origen?->codigo . ' · ' . $t->origen?->nombre],
                'destino' => ['id' => $t->destino_id, 'nombre' => $t->destino?->codigo . ' · ' . $t->destino?->nombre],
                'estado' => is_object($t->estado) ? $t->estado->value : $t->estado,
                'motivo' => $t->motivo,
                'observaciones' => $t->observaciones,
                'solicitante' => $t->solicitante?->name,
                'ejecutor' => $t->ejecutor?->name,
                'fecha_solicitud' => optional($t->fecha_solicitud)->format('Y-m-d'),
                'fecha_envio' => optional($t->fecha_envio)->format('Y-m-d H:i'),
                'fecha_ejecucion' => optional($t->fecha_ejecucion)->format('Y-m-d H:i'),
                'items' => $t->items->map(fn ($it) => [
                    'id' => $it->id,
                    'variante_id' => $it->variante_id,
                    'sku' => $it->variante?->codigo_barras,
                    'producto' => $it->variante?->producto?->nombre,
                    'detalle' => trim(($it->variante?->color_nombre ?? '') . ' ' . ($it->variante?->talla ?? '')),
                    'cantidad_solicitada' => (float) $it->cantidad_solicitada,
                    'cantidad_ejecutada' => (float) $it->cantidad_ejecutada,
                    'notas' => $it->notas,
                ])->values(),
            ],
        ]);
    }

    public function trasladoItemGuardar(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate([
            'variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'cantidad' => ['required', 'numeric', 'min:0.0001', 'max:999999'],
            'notas' => ['nullable', 'string', 'max:200'],
        ]);
        $t = Traslado::findOrFail($id);
        abort_unless(
            (is_object($t->estado) ? $t->estado->value : $t->estado) === 'borrador',
            409, 'Sólo se editan items en estado Borrador.'
        );
        // scope por bodega
        $u = $r->user();
        if (! $u->esAracely()) {
            $bod = $u->bodegasAsignadasIds();
            abort_unless(in_array((int)$t->origen_id, $bod, true) && in_array((int)$t->destino_id, $bod, true), 403);
        }
        \App\Modules\Inventario\Models\TrasladoItem::updateOrCreate(
            ['traslado_id' => $t->id, 'variante_id' => (int) $data['variante_id']],
            ['cantidad_solicitada' => round((float) $data['cantidad'], 4), 'notas' => $data['notas'] ?? null]
        );
        return back()->with('success', 'Ítem guardado.');
    }

    public function trasladoItemEliminar(Request $r, int $id, int $itemId): RedirectResponse
    {
        $t = Traslado::findOrFail($id);
        abort_unless(
            (is_object($t->estado) ? $t->estado->value : $t->estado) === 'borrador',
            409, 'Sólo se editan items en estado Borrador.'
        );
        $u = $r->user();
        if (! $u->esAracely()) {
            $bod = $u->bodegasAsignadasIds();
            abort_unless(in_array((int)$t->origen_id, $bod, true) && in_array((int)$t->destino_id, $bod, true), 403);
        }
        \App\Modules\Inventario\Models\TrasladoItem::where('traslado_id', $t->id)->whereKey($itemId)->delete();
        return back()->with('success', 'Ítem eliminado.');
    }

    public function trasladoEnviar(int $id): RedirectResponse
    {
        $t = Traslado::findOrFail($id);
        try {
            app(\App\Modules\Inventario\Actions\EjecutarTraslado::class)->enviar($t);
            return back()->with('success', "Traslado {$t->numero} enviado.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function trasladoRecibir(int $id): RedirectResponse
    {
        $t = Traslado::findOrFail($id);
        try {
            app(\App\Modules\Inventario\Actions\EjecutarTraslado::class)->recibir($t);
            return back()->with('success', "Traslado {$t->numero} recibido.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function trasladoAnular(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate(['motivo' => ['required','string','min:5','max:500']]);
        $t = Traslado::findOrFail($id);
        try {
            app(\App\Modules\Inventario\Actions\EjecutarTraslado::class)->anular($t, $data['motivo']);
            return back()->with('success', "Traslado {$t->numero} anulado.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ---------- CONTEO SHOW + ACCIONES ----------
    public function conteoShow(int $id): Response
    {
        $toma = TomaFisica::with(['items.variante.producto', 'ubicacion', 'creador', 'cerrador'])->findOrFail($id);
        $estado = is_object($toma->estado) ? $toma->estado->value : $toma->estado;

        return Inertia::render('Inventario/Conteo/Show', [
            'toma' => [
                'id' => $toma->id, 'numero' => $toma->numero,
                'ubicacion' => $toma->ubicacion?->codigo . ' · ' . $toma->ubicacion?->nombre,
                'creador' => $toma->creador?->name,
                'cerrador' => $toma->cerrador?->name,
                'estado' => $estado,
                'tipo' => $toma->tipo, 'alcance' => $toma->alcance,
                'fecha_conteo' => optional($toma->fecha_conteo)->format('Y-m-d'),
                'cerrada_at' => optional($toma->cerrada_at)->format('Y-m-d H:i'),
                'items_diferentes' => (int) $toma->items_diferentes,
                'valor_ajuste' => (float) $toma->valor_ajuste,
                'items' => $toma->items->map(fn ($it) => [
                    'id' => $it->id,
                    'sku' => $it->variante?->codigo_barras,
                    'producto' => $it->variante?->producto?->nombre,
                    'detalle' => trim(($it->variante?->color_nombre ?? '') . ' ' . ($it->variante?->talla ?? '')),
                    'saldo_sistema' => (float) $it->saldo_sistema,
                    'cantidad_contada' => $it->cantidad_contada === null ? null : (float) $it->cantidad_contada,
                    'diferencia' => (float) $it->diferencia,
                    'costo_unit' => (float) $it->costo_unit,
                ])->values(),
            ],
        ]);
    }

    public function conteoIniciar(int $id): RedirectResponse
    {
        $toma = TomaFisica::findOrFail($id);
        try {
            \App\Modules\Inventario\Actions\PrepararTomaFisica::run($toma);
            return back()->with('success', "Toma {$toma->numero} lista para capturar.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function conteoItemGuardar(Request $r, int $id, int $itemId): RedirectResponse
    {
        $data = $r->validate([
            'cantidad_contada' => ['nullable', 'numeric', 'min:0', 'max:999999'],
        ]);
        $toma = TomaFisica::findOrFail($id);
        $item = \App\Modules\Inventario\Models\TomaFisicaItem::whereKey($itemId)->firstOrFail();
        abort_unless($item->toma_id === $toma->id, 403);
        abort_unless(
            (is_object($toma->estado) ? $toma->estado->value : $toma->estado) === 'en_conteo',
            409, 'Sólo se captura mientras la toma está En Conteo.'
        );
        $item->cantidad_contada = ($data['cantidad_contada'] === null || $data['cantidad_contada'] === '')
            ? null : round((float) $data['cantidad_contada'], 4);
        $item->save();
        return back()->with('success', 'Cantidad guardada.');
    }

    public function conteoCerrar(int $id): RedirectResponse
    {
        $toma = TomaFisica::findOrFail($id);
        try {
            \App\Modules\Inventario\Actions\CerrarTomaFisica::run($toma);
            return back()->with('success', "Toma {$toma->numero} cerrada.");
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ---------- ALERTAS CRUD ----------
    public function alertaGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer', 'exists:alertas_stock_config,id'],
            'variante_id' => ['required', 'integer', 'exists:producto_variantes,id'],
            'ubicacion_id' => ['nullable', 'integer', 'exists:inventario_ubicaciones,id'],
            'stock_minimo' => ['required', 'integer', 'min:0'],
            'punto_reorden' => ['nullable', 'integer', 'min:0'],
            'cantidad_reorden' => ['nullable', 'integer', 'min:0'],
            'notificar_email' => ['boolean'],
            'notificar_whatsapp' => ['boolean'],
            'activa' => ['boolean'],
        ]);
        if (! empty($data['id'])) {
            $a = AlertaStockConfig::findOrFail($data['id']);
            $a->fill($data)->save();
        } else {
            AlertaStockConfig::updateOrCreate(
                ['variante_id' => $data['variante_id'], 'ubicacion_id' => $data['ubicacion_id'] ?? null],
                $data
            );
        }
        return back()->with('success', 'Alerta guardada.');
    }

    public function alertaEliminar(int $id): RedirectResponse
    {
        AlertaStockConfig::whereKey($id)->delete();
        return back()->with('success', 'Alerta eliminada.');
    }

    public function trasladoCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'origen_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
            'destino_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id', 'different:origen_id'],
            'motivo' => ['required', 'string', 'max:200'],
            'observaciones' => ['nullable', 'string', 'max:500'],
        ]);

        // Re-audit M3 λ (SEG-C2) · Alistador scoped por bodegas asignadas.
        //   Aracely/Gerencia pasan; Alistador debe tener AMBAS bodegas en su
        //   perfil. Antes cualquier Alistador podía drenar bodega ajena.
        $u = $r->user();
        if (! $u->esAracely()) {
            $bodegas = $u->bodegasAsignadasIds();
            abort_unless(
                in_array((int) $data['origen_id'], $bodegas, true)
                && in_array((int) $data['destino_id'], $bodegas, true),
                403,
                'Sólo puedes crear traslados entre bodegas asignadas a tu perfil.'
            );
        }

        // Re-audit M3 PATRÓN η · `Traslado::siguienteNumero()` atómico TRA-YYYY-NNNNNN
        //   (antes `count()+1` con formato TR-ymd-NNN incoherente).
        $data['numero'] = Traslado::siguienteNumero();
        $data['solicitado_por'] = auth()->id();
        $data['fecha_solicitud'] = now('America/Bogota')->toDateString();
        $data['estado'] = \App\Modules\Inventario\Enums\EstadoTraslado::Borrador;
        $t = Traslado::create($data);
        return back()->with('success', "Traslado {$t->numero} creado.");
    }

    // ---------- ALERTAS STOCK ----------
    public function alertasIndex(): Response
    {
        $configs = AlertaStockConfig::with(['variante.producto:id,nombre', 'ubicacion:id,codigo,nombre'])
            ->orderByDesc('id')->paginate(30);
        $ubicaciones = InventarioUbicacion::where('activa', true)->orderBy('codigo')->get(['id','codigo','nombre']);
        return Inertia::render('Inventario/Alertas/Index', [
            'alertas' => $configs->through(fn ($a) => [
                'id' => $a->id,
                'variante_id' => $a->variante_id,
                'producto' => $a->variante?->producto?->nombre,
                'sku' => $a->variante?->codigo_barras,
                'ubicacion_id' => $a->ubicacion_id,
                'ubicacion' => $a->ubicacion ? ($a->ubicacion->codigo . ' · ' . $a->ubicacion->nombre) : '— Global —',
                'minimo' => (int) $a->stock_minimo,
                'reorden' => (int) $a->punto_reorden,
                'cantidad_reorden' => (int) $a->cantidad_reorden,
                'notificar_email' => (bool) $a->notificar_email,
                'notificar_whatsapp' => (bool) $a->notificar_whatsapp,
                'activa' => (bool) $a->activa,
            ]),
            'ubicaciones' => $ubicaciones,
        ]);
    }

    // ---------- AUTOCOMPLETE VARIANTES ----------
    public function buscarVariantes(Request $r)
    {
        $q = trim((string) $r->input('q', ''));
        if (strlen($q) < 2) return response()->json([]);
        $vars = ProductoVariante::query()
            ->with('producto:id,referencia,nombre')
            ->where(function ($x) use ($q) {
                $x->where('codigo_barras', 'like', "%{$q}%")
                  ->orWhereHas('producto', fn ($p) => $p->where('nombre', 'like', "%{$q}%")->orWhere('referencia', 'like', "%{$q}%"));
            })
            ->limit(15)->get();
        return response()->json($vars->map(fn ($v) => [
            'id' => $v->id,
            'sku' => $v->codigo_barras,
            'producto' => $v->producto?->nombre,
            'detalle' => trim(($v->color_nombre ?? '') . ' ' . ($v->talla ?? '')),
            'label' => trim(($v->producto?->nombre ?? '?') . ' · ' . ($v->color_nombre ?? '') . ' ' . ($v->talla ?? '') . ' [' . $v->codigo_barras . ']'),
        ]));
    }
}
