<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiDevolucion;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DropiController extends Controller implements HasMiddleware
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
        return Inertia::render('Dropi/Index', [
            'tab' => (string) $request->input('tab', 'dashboard'),
            'kpis' => $this->kpis(),
            'porEstado' => $this->porEstado(),
            'pedidosRecientes' => $this->pedidosRecientes(),
            'cortes' => $this->cortes(),
            'devoluciones' => $this->devoluciones(),
            'wallet' => $this->wallet(),
            'auditorias' => $this->auditorias(),
        ]);
    }

    private function kpis(): array
    {
        $hoy = today('America/Bogota');
        $inicioMes = $hoy->copy()->startOfMonth();
        return [
            'pedidos_mes' => DropiPedido::whereBetween('created_at', [$inicioMes, $hoy->copy()->endOfDay()])->count(),
            'pendientes' => DropiPedido::whereIn('estado', [EstadoPedidoDropi::Pending, EstadoPedidoDropi::Alistando])->count(),
            'entregados_mes' => DropiPedido::whereBetween('entregado_at', [$inicioMes, $hoy->copy()->endOfDay()])->count(),
            'ventas_mes' => (float) DropiPedido::whereBetween('pagado_at', [$inicioMes, $hoy->copy()->endOfDay()])
                ->where('estado', EstadoPedidoDropi::Pagado)->sum('monto_esperado_proveedor'),
        ];
    }

    private function porEstado(): array
    {
        return DropiPedido::query()
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->map(fn ($r) => [
                'estado' => is_object($r->estado) ? $r->estado->value : $r->estado,
                'label' => (EstadoPedidoDropi::tryFrom(is_object($r->estado) ? $r->estado->value : $r->estado))?->label() ?? $r->estado,
                'total' => (int) $r->total,
            ])->all();
    }

    private function pedidosRecientes(): array
    {
        return DropiPedido::query()
            ->with('corte:id,numero')
            ->orderByDesc('created_at')->limit(50)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'guia' => $p->guia,
                'dropi_id' => $p->dropi_orden_id,
                'cliente' => $p->cliente_nombre,
                'ciudad' => $p->cliente_ciudad,
                'transportadora' => $p->transportadora,
                'corte' => $p->corte?->numero,
                'estado' => is_object($p->estado) ? $p->estado->value : $p->estado,
                'estado_label' => (EstadoPedidoDropi::tryFrom(is_object($p->estado) ? $p->estado->value : $p->estado))?->label() ?? $p->estado,
                'monto' => (float) $p->monto_esperado_proveedor,
                'created' => $p->created_at?->toDateString(),
            ])->all();
    }

    private function cortes(): array
    {
        return DropiCorte::query()
            ->withCount('pedidos')
            ->orderByDesc('id')->limit(20)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'numero' => $c->numero,
                'fecha_desde' => $c->fecha_desde?->toDateString(),
                'fecha_hasta' => $c->fecha_hasta?->toDateString(),
                'estado' => $c->estado,
                'pedidos_count' => (int) $c->pedidos_count,
            ])->all();
    }

    private function devoluciones(): array
    {
        return DropiDevolucion::query()
            ->with(['pedido:id,guia,cliente_nombre', 'decidioAlistador:id,name'])
            ->orderByDesc('recibido_at')->limit(30)
            ->get()
            ->map(fn ($d) => [
                'id' => $d->id,
                'pedido_id' => $d->pedido_id,
                'guia' => $d->pedido?->guia,
                'cliente' => $d->pedido?->cliente_nombre,
                'recibido' => $d->recibido_at?->toDateString(),
                'destino' => $d->destino_inventario,
                'nc' => (bool) $d->genero_nota_credito,
                'notas' => $d->notas,
                'por' => $d->decidioAlistador?->name,
            ])->all();
    }

    private function wallet(): array
    {
        $total = (float) DropiWalletMovimiento::sum('monto');
        // Re-audit H3 func · SOLO restar sanciones cuyo monto NO entró al wallet.
        // `diferencia_precio` ya está reflejada en el `wallet.monto` menor recibido
        // (restarla dos veces = doble contabilidad). `pago_sobre_devuelto` y
        // `categoria_explicita` (indemnizaciones) sí requieren resta explícita.
        $sanciones = (float) \App\Modules\Dropi\Models\DropiSancion::query()
            ->whereIn('tipo', ['pago_sobre_devuelto', 'categoria_explicita'])
            ->sum('diferencia');
        $saldoNeto = $total - $sanciones;
        return [
            'saldo' => $saldoNeto,
            'saldo_bruto' => $total,
            'sanciones_total' => $sanciones,
            'movimientos' => DropiWalletMovimiento::orderByDesc('fecha')->limit(20)->get()->map(function ($m) {
                $tipo = is_object($m->tipo) ? ($m->tipo->value ?? (string) $m->tipo) : (string) $m->tipo;
                $fuente = is_array($m->fuente) ? json_encode($m->fuente, JSON_UNESCAPED_UNICODE) : (string) ($m->fuente ?? '');
                $desc = trim(($m->categoria ?? '') . ($fuente !== '' ? ' · ' . $fuente : ''), ' ·');
                return [
                    'id' => $m->id,
                    'fecha' => $m->fecha instanceof \Carbon\Carbon ? $m->fecha->toDateString() : (string) $m->fecha,
                    'tipo' => $tipo,
                    'monto' => (float) $m->monto,
                    'referencia' => $m->dropi_movimiento_id,
                    'descripcion' => $desc,
                ];
            })->all(),
        ];
    }

    /** Auditorías del negocio (mercancía fantasma + tránsito) — feeds el dashboard. */
    private function auditorias(): array
    {
        return [
            'fantasma' => \App\Modules\Dropi\Actions\AuditarMercanciaFantasma::resumen(),
            'transito' => \App\Modules\Dropi\Actions\AuditarMercanciaEnTransito::resumen(),
        ];
    }
}
