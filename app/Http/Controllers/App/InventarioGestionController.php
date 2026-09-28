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
        $variante = null;
        $movimientos = [];
        $saldoTotal = 0;
        $costoPromedio = 0;
        $valorStock = 0;

        if ($codigo) {
            $variante = ProductoVariante::with('producto:id,referencia,nombre')
                ->where('codigo_barras', $codigo)
                ->orWhereHas('producto', fn ($p) => $p->where('referencia', $codigo))
                ->first();
            if ($variante) {
                $u = $request->user();
                $q = InventarioMovimiento::where('variante_id', $variante->id)
                    ->with('ubicacion:id,codigo,nombre');
                if (! $u->esAracely()) {
                    $bodegas = $u->bodegasAsignadasIds();
                    if (empty($bodegas)) {
                        $q->whereRaw('1=0');
                    } else {
                        $q->whereIn('ubicacion_id', $bodegas);
                    }
                }
                $movs = $q->orderBy('created_at')->limit(500)->get();

                // Sprint 3 · Kardex FORMATO SIIGO · calcula:
                //   1. Costo promedio ponderado tras cada entrada.
                //   2. Valor de cada movimiento (cantidad × costo).
                //   3. Valor total inventario tras cada movimiento (saldo × costo_prom).
                //   4. Documento origen con label legible + link.
                //   5. Estado sync SIIGO (siigo_journal_id, color badge).
                $saldo = 0; $costoProm = 0;
                $movimientos = $movs->map(function ($m) use (&$saldo, &$costoProm) {
                    $cantidad = (int) $m->cantidad;
                    $costoUnit = (float) ($m->costo_unit ?? 0);
                    $valorMov = round(abs($cantidad) * $costoUnit, 2);

                    // Costo promedio ponderado: solo se recalcula en ENTRADAS con costo.
                    if ($cantidad > 0 && $costoUnit > 0) {
                        $saldoAnterior = $saldo;
                        $valorAnterior = $saldoAnterior * $costoProm;
                        $valorNuevo = $valorAnterior + ($cantidad * $costoUnit);
                        $saldoNuevo = $saldoAnterior + $cantidad;
                        $costoProm = $saldoNuevo > 0 ? round($valorNuevo / $saldoNuevo, 4) : 0;
                    }

                    $saldo += $cantidad;
                    $valorStock = round($saldo * $costoProm, 2);

                    // SIIGO badge según siigo_journal_id.
                    $siigoColor = $m->siigo_journal_id
                        ? 'emerald'
                        : (in_array($m->tipo, ['traslado_salida', 'traslado_entrada', 'merma', 'faltante', 'sobrante', 'ajuste_toma_fisica'], true) ? 'amber' : 'gray');

                    return [
                        'id' => $m->id,
                        'fecha' => $m->created_at?->format('Y-m-d H:i'),
                        'tipo' => $m->tipo,
                        'ubicacion' => $m->ubicacion?->codigo . ' · ' . $m->ubicacion?->nombre,
                        'cantidad' => $cantidad,
                        'costo_unit' => $costoUnit,
                        'valor_mov' => $valorMov,
                        'saldo' => $saldo,
                        'costo_prom' => $costoProm,
                        'valor_stock' => $valorStock,
                        'referencia_tipo' => $m->referencia_tipo ? class_basename($m->referencia_tipo) : null,
                        'referencia_id' => $m->referencia_id,
                        'referencia_label' => $this->labelReferencia($m->referencia_tipo, $m->referencia_id),
                        'referencia_link' => $this->linkReferencia($m->referencia_tipo, $m->referencia_id),
                        'notas' => $m->notas,
                        'siigo_journal_id' => $m->siigo_journal_id,
                        'siigo_sync_hace' => $m->siigo_sync_at?->diffForHumans(),
                        'siigo_color' => $siigoColor,
                    ];
                });
                $saldoTotal = $saldo;
                $costoPromedio = $costoProm;
                $valorStock = round($saldo * $costoProm, 2);
            }
        }

        return Inertia::render('Inventario/Kardex', [
            'codigo' => $codigo,
            'variante' => $variante ? [
                'id' => $variante->id, 'codigo' => $variante->codigo_barras,
                'producto' => $variante->producto?->nombre, 'referencia' => $variante->producto?->referencia,
                'detalle' => trim(($variante->color_nombre ?? '') . ' ' . ($variante->talla ?? '')),
                'siigo_id' => $variante->siigo_id,
                'siigo_code' => $variante->siigo_code,
            ] : null,
            'movimientos' => $movimientos,
            'saldoTotal' => $saldoTotal,
            'costoPromedio' => $costoPromedio,
            'valorStock' => $valorStock,
        ]);
    }

    /**
     * Sprint 3 · Kardex SIIGO · label legible del documento origen.
     */
    private function labelReferencia(?string $tipo, ?int $id): string
    {
        if (! $tipo || ! $id) return '—';
        return match (class_basename($tipo)) {
            'RecepcionCompra' => "Recepción #{$id}",
            'FacturaVenta' => "Factura #{$id}",
            'Traslado' => "Traslado #{$id}",
            'TomaFisica' => "Toma física #{$id}",
            'DropiPedido' => "Pedido Dropi #{$id}",
            default => class_basename($tipo) . " #{$id}",
        };
    }

    /**
     * Sprint 3 · Kardex SIIGO · link Vue al documento origen.
     */
    private function linkReferencia(?string $tipo, ?int $id): ?string
    {
        if (! $tipo || ! $id) return null;
        return match (class_basename($tipo)) {
            'RecepcionCompra' => "/app/compras/recepcion/{$id}",
            'FacturaVenta' => "/app/cartera/facturas/{$id}",
            'Traslado' => "/app/inventario/traslados/{$id}",
            'TomaFisica' => "/app/inventario/conteos/{$id}",
            default => null,
        };
    }

    // ---------- REPORTE STOCK POR BODEGA/UBICACIÓN ----------
    public function reporteStock(): Response
    {
        // Sprint 3 · F.2 · Reporte stock FORMATO SIIGO.
        // Añade: valorización por bodega, costo promedio agregado, valor total inventario,
        // costo promedio y valor por SKU en el top.

        // Costo promedio por variante (basado en entradas con costo).
        $costoPromedios = $this->calcularCostosPromedio();

        // Total unidades por ubicación
        $saldos = InventarioMovimiento::selectRaw('ubicacion_id, SUM(cantidad) as total, COUNT(DISTINCT variante_id) as skus')
            ->groupBy('ubicacion_id')
            ->having('total', '!=', 0)
            ->get()->keyBy('ubicacion_id');

        // Valorización por ubicación: Σ (saldo × costo_prom) por cada SKU en esa ubicación.
        $valorPorUbicacion = $this->calcularValorPorUbicacion($costoPromedios);

        $ubicaciones = InventarioUbicacion::where('activa', true)->orderBy('codigo')->get()
            ->map(function ($u) use ($saldos, $valorPorUbicacion) {
                $r = $saldos->get($u->id);
                return [
                    'codigo' => $u->codigo, 'nombre' => $u->nombre,
                    'categoria' => is_object($u->categoria) ? $u->categoria->value : $u->categoria,
                    'skus' => (int) ($r->skus ?? 0),
                    'unidades' => (int) ($r->total ?? 0),
                    'valor' => (float) ($valorPorUbicacion[$u->id] ?? 0),
                ];
            });

        // Top 30 variantes con más stock · agregando costo_prom y valor
        $topStock = InventarioMovimiento::selectRaw('variante_id, SUM(cantidad) as total')
            ->whereNotNull('variante_id')
            ->groupBy('variante_id')->having('total', '>', 0)->orderByDesc('total')->limit(30)->get();

        $ids = $topStock->pluck('variante_id')->filter()->all();
        $vars = ProductoVariante::with('producto:id,referencia,nombre')->whereIn('id', $ids)->get()->keyBy('id');

        $top = $topStock->map(function ($r) use ($vars, $costoPromedios) {
            $vid = (int) $r->variante_id;
            $v = $vars->get($vid);
            $costoProm = (float) ($costoPromedios[$vid] ?? 0);
            $total = (int) $r->total;
            return [
                'codigo' => $v?->codigo_barras ?? '—',
                'producto' => $v?->producto?->nombre ?? '—',
                'total' => $total,
                'costo_prom' => $costoProm,
                'valor' => round($total * $costoProm, 2),
                'siigo_code' => $v?->siigo_code,
            ];
        });

        return Inertia::render('Inventario/ReporteStock', [
            'ubicaciones' => $ubicaciones->values(),
            'topStock' => $top,
            'kpis' => [
                'ubicaciones_activas' => $ubicaciones->count(),
                'unidades_totales' => (int) $ubicaciones->sum('unidades'),
                'skus_con_stock' => $topStock->count(),
                'valor_total_inventario' => (float) $ubicaciones->sum('valor'),
            ],
        ]);
    }

    /**
     * Sprint 3 · F.2 · costo promedio ponderado por variante desde ENTRADAS
     * (traversal 1× de inventario_movimientos con costo_unit > 0).
     * Devuelve [variante_id => costo_prom].
     */
    private function calcularCostosPromedio(): array
    {
        $entradas = InventarioMovimiento::selectRaw(
            'variante_id, SUM(cantidad * costo_unit) as valor_ent, SUM(cantidad) as cant_ent'
        )
            ->where('cantidad', '>', 0)
            ->where('costo_unit', '>', 0)
            ->groupBy('variante_id')
            ->get()->keyBy('variante_id');

        $out = [];
        foreach ($entradas as $vid => $r) {
            $out[$vid] = $r->cant_ent > 0 ? round((float) $r->valor_ent / (float) $r->cant_ent, 4) : 0;
        }
        return $out;
    }

    /**
     * Sprint 3 · F.2 · valor de inventario por ubicación · Σ(saldo_variante × costo_prom).
     * Devuelve [ubicacion_id => valor].
     */
    private function calcularValorPorUbicacion(array $costos): array
    {
        $saldos = InventarioMovimiento::selectRaw('ubicacion_id, variante_id, SUM(cantidad) as saldo')
            ->groupBy('ubicacion_id', 'variante_id')
            ->having('saldo', '>', 0)
            ->get();
        $out = [];
        foreach ($saldos as $r) {
            $costo = (float) ($costos[$r->variante_id] ?? 0);
            $out[$r->ubicacion_id] = ($out[$r->ubicacion_id] ?? 0) + ((int) $r->saldo * $costo);
        }
        return array_map(fn ($v) => round($v, 2), $out);
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

    /**
     * Sprint 3 · A.4 · Elimina una toma que aún no ha empezado (estado=borrador,
     * cero items contados). Preserva integridad contable: si hay conteos ya
     * hechos, no elimina, sugerir anular en su lugar.
     */
    public function conteoEliminar(int $id): RedirectResponse
    {
        $toma = TomaFisica::findOrFail($id);
        if ($toma->estado !== 'borrador') {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "Solo se pueden eliminar tomas en BORRADOR. Esta está {$toma->estado} · usa Anular.",
            ]);
        }
        $contados = $toma->items()->whereNotNull('cantidad_contada')->count();
        if ($contados > 0) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "La toma tiene {$contados} items ya contados · usa Anular en vez de eliminar.",
            ]);
        }
        $num = $toma->numero;
        $toma->items()->delete();
        $toma->delete();
        return back()->with('flash', ['type' => 'success', 'message' => "Toma {$num} eliminada."]);
    }

    /**
     * Sprint 3 · A.4 · Anula una toma en curso (estado=en_conteo).
     * NO borra los items — deja registro de auditoría.
     */
    public function conteoAnular(Request $r, int $id): RedirectResponse
    {
        $data = $r->validate(['motivo' => ['required', 'string', 'min:10', 'max:300']]);
        $toma = TomaFisica::findOrFail($id);

        if (! in_array($toma->estado, ['borrador', 'en_conteo'], true)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => "No se puede anular una toma {$toma->estado}.",
            ]);
        }

        $toma->estado = 'anulada';
        $toma->observaciones = trim(($toma->observaciones ?? '') . "\n[ANULADA " . now()->toDateString() . " por " . auth()->user()?->name . "] " . $data['motivo']);
        $toma->save();

        return back()->with('flash', ['type' => 'success', 'message' => "Toma {$toma->numero} anulada."]);
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
