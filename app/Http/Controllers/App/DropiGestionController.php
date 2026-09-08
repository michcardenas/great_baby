<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * DRP-B · CRUD Vue de gestión Dropi (paridad Filament):
 *  - Pedidos: editar estado + datos cliente
 *  - Cortes: crear, editar, cerrar/liquidar
 *  - Wallet movimientos: crear ajuste manual
 *  - Ubicaciones inventario: CRUD completo
 */
class DropiGestionController extends Controller implements HasMiddleware
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

    // ---------- PEDIDO (edit) ----------
    public function pedidoEditar(int $pedido): Response
    {
        $p = DropiPedido::with(['items.variante.producto', 'corte:id,numero'])->findOrFail($pedido);
        return Inertia::render('Dropi/Pedido/Editar', [
            'pedido' => [
                'id' => $p->id,
                'guia' => $p->guia,
                'dropi_orden_id' => $p->dropi_orden_id,
                'transportadora' => $p->transportadora,
                'estado' => is_object($p->estado) ? $p->estado->value : $p->estado,
                'cliente_nombre' => $p->cliente_nombre,
                'cliente_doc' => $p->cliente_doc,
                'cliente_telefono' => $p->cliente_telefono,
                'cliente_direccion' => $p->cliente_direccion,
                'cliente_ciudad' => $p->cliente_ciudad,
                'cliente_depto' => $p->cliente_depto,
                'monto_esperado_proveedor' => (float) $p->monto_esperado_proveedor,
                'ganancia_vendedor' => (float) $p->ganancia_vendedor,
                'flete_transportadora' => (float) $p->flete_transportadora,
                'corte_numero' => $p->corte?->numero,
                'items' => $p->items->map(fn ($i) => [
                    'sku' => $i->sku_dropi,
                    'descripcion' => trim(($i->variante?->producto?->nombre ?? '') . ' · ' . ($i->variante?->color_nombre ?? '') . ' ' . ($i->variante?->talla ?? '')),
                    'cantidad' => (int) $i->cantidad,
                    'precio' => (float) $i->precio_proveedor_unit,
                ])->all(),
            ],
            'estadosDisponibles' => array_map(fn ($c) => ['value' => $c->value, 'label' => $c->label() ?? $c->name], EstadoPedidoDropi::cases()),
        ]);
    }

    public function pedidoActualizar(Request $r, int $pedido): RedirectResponse
    {
        $data = $r->validate([
            'estado' => ['required', 'string'],
            'cliente_nombre' => ['nullable', 'string', 'max:200'],
            'cliente_telefono' => ['nullable', 'string', 'max:50'],
            'cliente_direccion' => ['nullable', 'string', 'max:500'],
            'cliente_ciudad' => ['nullable', 'string', 'max:100'],
            'cliente_depto' => ['nullable', 'string', 'max:100'],
            'transportadora' => ['nullable', 'string', 'max:100'],
            'monto_esperado_proveedor' => ['nullable', 'numeric', 'min:0'],
        ]);
        $p = DropiPedido::findOrFail($pedido);
        $p->update($data);
        return back()->with('success', 'Pedido actualizado.');
    }

    // ---------- CORTES ----------
    public function cortesIndex(): Response
    {
        $cortes = DropiCorte::withCount('pedidos')->orderByDesc('id')->paginate(30);
        return Inertia::render('Dropi/Corte/Index', [
            'cortes' => $cortes->through(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'fecha' => $c->fecha?->toDateString(),
                'estado' => $c->estado,
                'pedidos_count' => (int) $c->pedidos_count,
                'pedidos_totales' => (int) $c->pedidos_totales,
                'cerrado_at' => $c->cerrado_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function corteCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'numero' => ['required', 'integer', 'min:1'],
            'fecha' => ['required', 'date'],
        ]);
        DropiCorte::create([
            'numero' => $data['numero'],
            'fecha' => $data['fecha'],
            'estado' => 'abierto',
        ]);
        return back()->with('success', 'Corte creado.');
    }

    public function corteCerrar(int $corte): RedirectResponse
    {
        $c = DropiCorte::findOrFail($corte);
        abort_if($c->estado === 'cerrado', 422, 'Corte ya cerrado.');
        $c->update([
            'estado' => 'cerrado',
            'cerrado_at' => now(),
            'cerrado_por' => auth()->id(),
        ]);
        return back()->with('success', "Corte {$c->numero} cerrado.");
    }

    // ---------- WALLET MOVIMIENTOS ----------
    public function walletIndex(): Response
    {
        $movs = DropiWalletMovimiento::orderByDesc('fecha')->paginate(30);
        return Inertia::render('Dropi/Wallet/Index', [
            'movimientos' => $movs->through(function ($m) {
                $tipo = is_object($m->tipo) ? ($m->tipo->value ?? (string) $m->tipo) : (string) $m->tipo;
                $fuente = is_array($m->fuente) ? json_encode($m->fuente, JSON_UNESCAPED_UNICODE) : (string) ($m->fuente ?? '');
                return [
                    'id' => $m->id,
                    'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : (string) $m->fecha,
                    'tipo' => $tipo,
                    'monto' => (float) $m->monto,
                    'pedido_id' => $m->pedido_id,
                    'categoria' => $m->categoria,
                    'fuente' => $fuente,
                    'referencia' => $m->dropi_movimiento_id,
                ];
            }),
        ]);
    }

    public function walletCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'fecha' => ['required', 'date'],
            'tipo' => ['required', 'string', 'max:50'],
            'monto' => ['required', 'numeric'],
            'pedido_id' => ['nullable', 'integer', 'exists:dropi_pedidos,id'],
            'categoria' => ['nullable', 'string', 'max:100'],
            'dropi_movimiento_id' => ['nullable', 'string', 'max:100'],
        ]);
        DropiWalletMovimiento::create([
            ...$data,
            'fuente' => ['origen' => 'ajuste_manual', 'user_id' => auth()->id()],
        ]);
        return back()->with('success', 'Movimiento wallet registrado.');
    }

    // ---------- UBICACIONES INVENTARIO ----------
    public function ubicacionesIndex(): Response
    {
        $ubicaciones = InventarioUbicacion::orderBy('codigo')->paginate(50);
        return Inertia::render('Dropi/Ubicaciones/Index', [
            'ubicaciones' => $ubicaciones->through(fn ($u) => [
                'id' => $u->id,
                'codigo' => $u->codigo,
                'nombre' => $u->nombre,
                'categoria' => is_object($u->categoria) ? $u->categoria->value : $u->categoria,
                'disponible_para_venta' => (bool) $u->disponible_para_venta,
                'activa' => (bool) $u->activa,
                'notas' => $u->notas,
            ]),
        ]);
    }

    public function ubicacionGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer', 'exists:inventario_ubicaciones,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'categoria' => ['nullable', 'string', 'max:50'],
            'disponible_para_venta' => ['boolean'],
            'activa' => ['boolean'],
            'notas' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['id'] ?? null) {
            InventarioUbicacion::findOrFail($data['id'])->update($data);
        } else {
            InventarioUbicacion::create($data);
        }
        return back()->with('success', 'Ubicación guardada.');
    }

    public function ubicacionEliminar(int $ubicacion): RedirectResponse
    {
        InventarioUbicacion::findOrFail($ubicacion)->delete();
        return back()->with('success', 'Ubicación eliminada.');
    }
}
