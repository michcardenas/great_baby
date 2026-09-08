<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiAlistadorLock;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controladora de operación Dropi (paridad con Filament Vue migrado):
 *  - Vista del Alistador (recolección + empaque)
 *  - Registrar Devolución
 *  - Escáner cámara
 *  - Discrepancias wallet
 */
class DropiOperacionController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                $u = $r->user();
                abort_unless($u && ($u->esAracely() || method_exists($u, 'esAlistador') && $u->esAlistador()), 403);
                return $next($r);
            }),
        ];
    }

    // ============================================================
    // VISTA DEL ALISTADOR
    // ============================================================

    public function alistador(Request $request): Response
    {
        $modo = (string) $request->input('modo', 'recoleccion');
        if (! in_array($modo, ['recoleccion', 'empaque'], true)) $modo = 'recoleccion';

        $corte = DropiCorte::orderByDesc('fecha')->orderByDesc('numero')->first();
        $corteId = $corte?->id;

        $recoleccion = [];
        if ($corteId && $modo === 'recoleccion') {
            $pedidos = DropiPedido::where('corte_id', $corteId)
                ->whereNotIn('estado', [
                    EstadoPedidoDropi::Empacado, EstadoPedidoDropi::Despachado,
                    EstadoPedidoDropi::Entregado, EstadoPedidoDropi::Pagado,
                    EstadoPedidoDropi::CanceladoDropi, EstadoPedidoDropi::CanceladoGb,
                    EstadoPedidoDropi::Devuelto,
                ])
                ->with(['items.variante.producto'])
                ->get();
            $grupos = [];
            foreach ($pedidos as $ped) {
                foreach ($ped->items as $it) {
                    $key = $it->sku_dropi ?: 'sin-sku';
                    $grupos[$key] ??= [
                        'sku' => $key,
                        'producto' => $it->variante?->producto?->nombre ?? 'SKU sin match',
                        'referencia' => $it->variante?->producto?->referencia ?? '—',
                        'variante' => trim(($it->variante?->color_nombre ?? '') . ' ' . ($it->variante?->diseno_nombre ?? '') . ' ' . ($it->variante?->talla ?? '')),
                        'total' => 0,
                    ];
                    $grupos[$key]['total'] += (int) $it->cantidad;
                }
            }
            $recoleccion = array_values($grupos);
        }

        $empaque = [];
        if ($corteId && $modo === 'empaque') {
            $bloqueadosPorOtros = DropiAlistadorLock::where('alistador_id', '!=', auth()->id())
                ->where('heartbeat_at', '>=', now()->subMinutes(10))
                ->pluck('pedido_id');

            $empaque = DropiPedido::where('corte_id', $corteId)
                ->whereIn('estado', [EstadoPedidoDropi::Pending, EstadoPedidoDropi::Alistando])
                ->whereNotIn('id', $bloqueadosPorOtros)
                ->orderBy('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'guia' => $p->guia,
                    'cliente' => $p->cliente_nombre,
                    'ciudad' => $p->cliente_ciudad,
                    'monto' => (float) $p->monto_esperado_proveedor,
                    'estado' => is_object($p->estado) ? $p->estado->value : $p->estado,
                ])->all();
        }

        return Inertia::render('Dropi/Alistador', [
            'modo' => $modo,
            'corte' => $corte ? ['id' => $corte->id, 'numero' => $corte->numero, 'fecha' => $corte->fecha?->toDateString()] : null,
            'recoleccion' => $recoleccion,
            'empaque' => $empaque,
        ]);
    }

    public function tomarPedido(int $pedidoId): RedirectResponse
    {
        $userId = auth()->id();
        DB::transaction(function () use ($pedidoId, $userId) {
            $lock = DropiAlistadorLock::firstOrCreate(['pedido_id' => $pedidoId], ['alistador_id' => $userId]);
            if ($lock->alistador_id !== $userId) {
                abort(409, 'Otro alistador ya está empacando este pedido.');
            }
            $lock->update(['heartbeat_at' => now()]);
            $p = DropiPedido::find($pedidoId);
            if ($p && $p->estado !== EstadoPedidoDropi::Alistando) {
                $anterior = is_object($p->estado) ? $p->estado->value : $p->estado;
                $p->update(['estado' => EstadoPedidoDropi::Alistando]);
                DropiEstadoBitacora::create([
                    'pedido_id' => $pedidoId,
                    'estado_desde' => $anterior,
                    'estado_hasta' => EstadoPedidoDropi::Alistando->value,
                    'fuente' => 'manual',
                    'user_id' => $userId,
                ]);
            }
        });
        return back()->with('success', 'Pedido tomado');
    }

    public function empacarPedido(int $pedidoId): RedirectResponse
    {
        $userId = auth()->id();
        DB::transaction(function () use ($pedidoId, $userId) {
            $miLock = DropiAlistadorLock::where('pedido_id', $pedidoId)->where('alistador_id', $userId)->exists();
            abort_unless($miLock, 409, 'El pedido lo tomó otro alistador o expiró.');
            $p = DropiPedido::find($pedidoId);
            if (! $p) return;
            $anterior = is_object($p->estado) ? $p->estado->value : $p->estado;
            $p->update(['estado' => EstadoPedidoDropi::Empacado]);
            DropiEstadoBitacora::create([
                'pedido_id' => $pedidoId, 'estado_desde' => $anterior,
                'estado_hasta' => EstadoPedidoDropi::Empacado->value, 'fuente' => 'manual', 'user_id' => $userId,
            ]);
            DropiAlistadorLock::where('pedido_id', $pedidoId)->delete();
        });
        return back()->with('success', 'Pedido empacado');
    }

    public function despacharPedido(int $pedidoId): RedirectResponse
    {
        DB::transaction(function () use ($pedidoId) {
            $p = DropiPedido::find($pedidoId);
            if (! $p) return;
            $anterior = is_object($p->estado) ? $p->estado->value : $p->estado;
            $p->update(['estado' => EstadoPedidoDropi::Despachado, 'despachado_at' => now()]);
            DropiEstadoBitacora::create([
                'pedido_id' => $pedidoId, 'estado_desde' => $anterior,
                'estado_hasta' => EstadoPedidoDropi::Despachado->value, 'fuente' => 'manual', 'user_id' => auth()->id(),
            ]);
        });
        // Job de WhatsApp si existe la clase
        if (class_exists(\App\Modules\Dropi\Jobs\NotificarClienteDespachoJob::class)) {
            \App\Modules\Dropi\Jobs\NotificarClienteDespachoJob::dispatch($pedidoId);
        }
        return back()->with('success', 'Despachado — WhatsApp encolada');
    }

    // ============================================================
    // REGISTRAR DEVOLUCIÓN
    // ============================================================

    public function devolucionForm(Request $request): Response
    {
        $guiaBuscar = trim((string) $request->input('guia', ''));
        $pedido = null;
        if ($guiaBuscar) {
            $pedido = DropiPedido::where('guia', $guiaBuscar)
                ->orWhere('dropi_orden_id', $guiaBuscar)
                ->with(['items.variante.producto'])
                ->first();
        }
        return Inertia::render('Dropi/Devolucion/Registrar', [
            'guiaBuscar' => $guiaBuscar,
            'pedido' => $pedido ? [
                'id' => $pedido->id,
                'guia' => $pedido->guia,
                'cliente' => $pedido->cliente_nombre,
                'ciudad' => $pedido->cliente_ciudad,
                'monto' => (float) $pedido->monto_esperado_proveedor,
                'estado' => is_object($pedido->estado) ? $pedido->estado->value : $pedido->estado,
                'items' => $pedido->items->map(fn ($i) => [
                    'sku' => $i->sku_dropi,
                    'descripcion' => trim(($i->variante?->producto?->nombre ?? '') . ' · ' . ($i->variante?->color_nombre ?? '') . ' ' . ($i->variante?->talla ?? '')),
                    'cantidad' => (int) $i->cantidad,
                ])->all(),
                'devolucion_existente' => DropiDevolucion::where('pedido_id', $pedido->id)->exists(),
            ] : null,
        ]);
    }

    public function devolucionGuardar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'pedido_id' => ['required', 'integer', 'exists:dropi_pedidos,id'],
            'destino_inventario' => ['required', 'in:reingresa,averiado,perdido'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($data) {
            $existe = DropiDevolucion::where('pedido_id', $data['pedido_id'])->exists();
            abort_if($existe, 422, 'Este pedido ya tiene una devolución registrada.');

            DropiDevolucion::create([
                'pedido_id' => $data['pedido_id'],
                'destino_inventario' => $data['destino_inventario'],
                'notas' => $data['notas'] ?? null,
                'recibido_at' => now(),
                'decision_por' => auth()->id(),
            ]);

            $pedido = DropiPedido::find($data['pedido_id']);
            if ($pedido) {
                $pedido->update([
                    'estado' => EstadoPedidoDropi::Devuelto,
                    'devuelto_at' => now(),
                ]);
            }
        });

        return redirect()->route('app.dropi.devolucion.registrar')->with('success', 'Devolución registrada.');
    }

    // ============================================================
    // ESCÁNER CÁMARA
    // ============================================================

    public function escanerCamara(): Response
    {
        return Inertia::render('Dropi/EscanerCamara');
    }

    public function escanerBuscar(Request $request)
    {
        $data = $request->validate([
            'codigo' => ['required', 'string', 'max:100'],
        ]);
        $codigo = trim($data['codigo']);
        $pedido = DropiPedido::where('guia', $codigo)
            ->orWhere('dropi_orden_id', $codigo)
            ->with(['items.variante.producto'])
            ->first();

        return response()->json([
            'encontrado' => (bool) $pedido,
            'pedido' => $pedido ? [
                'id' => $pedido->id,
                'guia' => $pedido->guia,
                'cliente' => $pedido->cliente_nombre,
                'ciudad' => $pedido->cliente_ciudad,
                'monto' => (float) $pedido->monto_esperado_proveedor,
                'estado' => is_object($pedido->estado) ? $pedido->estado->value : $pedido->estado,
                'items' => $pedido->items->map(fn ($i) => [
                    'sku' => $i->sku_dropi,
                    'descripcion' => trim(($i->variante?->producto?->nombre ?? '') . ' · ' . ($i->variante?->color_nombre ?? '') . ' ' . ($i->variante?->talla ?? '')),
                    'cantidad' => (int) $i->cantidad,
                ])->all(),
            ] : null,
        ]);
    }

    // ============================================================
    // DISCREPANCIAS WALLET
    // ============================================================

    public function discrepancias(): Response
    {
        // Cruce: pedidos Pagado sin movimiento wallet asociado
        $pedidosPagados = DropiPedido::where('estado', EstadoPedidoDropi::Pagado)
            ->whereNotNull('pagado_at')
            ->select('id', 'guia', 'dropi_orden_id', 'cliente_nombre', 'monto_esperado_proveedor', 'pagado_at')
            ->orderByDesc('pagado_at')
            ->limit(200)
            ->get();

        $pedidoIds = $pedidosPagados->pluck('id')->all();
        $conMovimiento = \App\Modules\Dropi\Models\DropiWalletMovimiento::whereIn('pedido_id', $pedidoIds)
            ->pluck('pedido_id')->flip();

        $sinCobrar = $pedidosPagados->filter(fn ($p) => ! isset($conMovimiento[$p->id]))
            ->map(fn ($p) => [
                'id' => $p->id, 'guia' => $p->guia, 'dropi_id' => $p->dropi_orden_id,
                'cliente' => $p->cliente_nombre, 'monto' => (float) $p->monto_esperado_proveedor,
                'pagado' => $p->pagado_at?->format('Y-m-d'),
            ])->values();

        // Movimientos wallet huérfanos (sin pedido)
        $movHuerfanos = \App\Modules\Dropi\Models\DropiWalletMovimiento::whereNull('pedido_id')
            ->orderByDesc('fecha')->limit(100)->get()
            ->map(function ($m) {
                $tipo = is_object($m->tipo) ? ($m->tipo->value ?? (string) $m->tipo) : (string) $m->tipo;
                return [
                    'id' => $m->id, 'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : (string) $m->fecha,
                    'tipo' => $tipo, 'monto' => (float) $m->monto, 'categoria' => $m->categoria,
                    'referencia' => $m->dropi_movimiento_id,
                ];
            });

        return Inertia::render('Dropi/Discrepancias', [
            'pedidosSinCobro' => $sinCobrar->all(),
            'movimientosHuerfanos' => $movHuerfanos->all(),
            'kpis' => [
                'pedidos_sin_cobro' => $sinCobrar->count(),
                'monto_faltante' => (float) $sinCobrar->sum('monto'),
                'movimientos_huerfanos' => $movHuerfanos->count(),
            ],
        ]);
    }
}
