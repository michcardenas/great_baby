<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\GarantiaTicket;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Portal\Models\PedidoCliente;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MEJORAS-A · Cosas del día: alertas proactivas para Aracely.
 * Todo lo que necesita atención HOY, en una sola pantalla.
 */
class CosasDelDiaController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esAracely(), 403);
            return $next($r);
        })];
    }

    public function __invoke(): Response
    {
        // Cache 5 min para no golpear BD en cada refresh
        $data = Cache::remember('cosas-del-dia', 300, function () {
            $hoy = today('America/Bogota');
            $ayer24h = now()->subHours(24);

            // 1. Pedidos B2B sin aprobar hace 24h+
            $pedidosB2BLentos = PedidoCliente::where('estado', 'enviado')
                ->where('created_at', '<', $ayer24h)
                ->with('contacto:id,nombre_completo,razon_social')
                ->orderBy('created_at')->limit(10)->get()
                ->map(fn ($p) => [
                    'id' => $p->id, 'numero' => $p->numero, 'url' => '/app/pedidos-b2b/' . $p->id,
                    'cliente' => $p->contacto?->razon_social ?: $p->contacto?->nombre_completo,
                    'total' => (float) $p->total,
                    'hace_horas' => (int) $p->created_at->diffInHours(now()),
                ]);

            // 2. Garantías sin decidir + con plazo vencido
            $garantiasSinDecidir = GarantiaTicket::whereIn('estado', ['abierto', 'en_revision'])
                ->limit(10)->get()
                ->map(fn ($g) => [
                    'id' => $g->id, 'numero' => $g->numero, 'url' => '/app/garantias/' . $g->id,
                    'cliente' => $g->cliente_nombre,
                    'plazo_vencido' => $g->plazo_concepto_at && $g->plazo_concepto_at->isPast(),
                    'hace_dias' => (int) $g->created_at?->diffInDays(now()),
                ]);

            // 3. Facturas vencidas grandes (top 10 por saldo)
            $facturasVencidas = FacturaVenta::where('estado', 'vencida')
                ->whereNull('deleted_at')->orderByDesc('saldo')->with('contacto:id,nombre_completo,razon_social,telefono')
                ->limit(10)->get()
                ->map(fn ($f) => [
                    'id' => $f->id, 'numero' => $f->numero, 'url' => '/app/facturas/' . $f->id,
                    'cliente' => $f->contacto?->razon_social ?: $f->contacto?->nombre_completo,
                    'telefono' => $f->contacto?->telefono,
                    'saldo' => (float) $f->saldo,
                    'dias_vencida' => (int) $f->fecha_vencimiento?->diffInDays($hoy, absolute: false),
                ]);

            // 4. Clientes dormidos (VIP sin comprar hace 30d+)
            $clientesDormidos = Contacto::where('activo', true)
                ->where('es_cliente_b2b', true)
                ->where(function ($q) {
                    $q->whereNull('ultima_compra_at')->orWhere('ultima_compra_at', '<', now()->subDays(30));
                })
                ->whereIn('segmento', ['VIP', 'Frecuente'])
                ->orderBy('ultima_compra_at')->limit(10)->get()
                ->map(fn ($c) => [
                    'id' => $c->id, 'url' => '/app/contactos/' . $c->id,
                    'nombre' => $c->razon_social ?: $c->nombre_completo,
                    'telefono' => $c->telefono, 'segmento' => $c->segmento,
                    'ultima_compra' => $c->ultima_compra_at?->format('Y-m-d') ?? 'nunca',
                    'dias_sin_comprar' => $c->ultima_compra_at ? (int) $c->ultima_compra_at->diffInDays(now()) : 999,
                ]);

            // 5. Dropi mercancía fantasma (marcada devuelta pero no llegó física)
            $fantasma = [];
            if (class_exists(\App\Modules\Dropi\Actions\AuditarMercanciaFantasma::class)) {
                try {
                    $fantasma = \App\Modules\Dropi\Actions\AuditarMercanciaFantasma::resumen()['ejemplos'] ?? [];
                    $fantasma = array_slice($fantasma, 0, 5);
                } catch (\Throwable) { $fantasma = []; }
            }

            // 6. Stock crítico (variantes con alerta activa + saldo bajo)
            $criticos = AlertaStockConfig::with('variante.producto:id,nombre')
                ->where('activa', true)
                ->limit(50)->get()
                ->filter(function ($a) {
                    $saldo = (int) InventarioMovimiento::where('variante_id', $a->variante_id)
                        ->where('ubicacion_id', $a->ubicacion_id)->sum('cantidad');
                    return $saldo <= (int) $a->stock_minimo;
                })
                ->take(10)
                ->map(fn ($a) => [
                    'sku' => $a->variante?->codigo_barras,
                    'producto' => $a->variante?->producto?->nombre,
                    'minimo' => (int) $a->stock_minimo,
                    'url' => '/app/inventario/kardex?codigo=' . urlencode($a->variante?->codigo_barras ?? ''),
                ])->values();

            // 7. Discrepancias Dropi wallet (dinero sin cobrar)
            $sinCobro = 0; $montoFaltante = 0;
            try {
                $ids = DropiPedido::where('estado', 'pagado')->whereNotNull('pagado_at')->pluck('id');
                $conMov = \App\Modules\Dropi\Models\DropiWalletMovimiento::whereIn('pedido_id', $ids)->pluck('pedido_id')->flip();
                $sinCobroModel = DropiPedido::where('estado', 'pagado')->whereNotIn('id', $conMov->keys())->get(['monto_esperado_proveedor']);
                $sinCobro = $sinCobroModel->count();
                $montoFaltante = (float) $sinCobroModel->sum('monto_esperado_proveedor');
            } catch (\Throwable) {}

            return [
                'generado_at' => now()->format('Y-m-d H:i'),
                'pedidosB2BLentos' => $pedidosB2BLentos,
                'garantiasSinDecidir' => $garantiasSinDecidir,
                'facturasVencidas' => $facturasVencidas,
                'clientesDormidos' => $clientesDormidos,
                'fantasma' => $fantasma,
                'criticos' => $criticos,
                'wallet' => ['sin_cobro' => $sinCobro, 'monto_faltante' => $montoFaltante],
            ];
        });

        return Inertia::render('Cosas/DelDia', $data);
    }
}
