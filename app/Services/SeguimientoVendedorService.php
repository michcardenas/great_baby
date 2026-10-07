<?php

namespace App\Services;

use App\Modules\Portal\Models\PedidoCliente;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * LOG-J1 bonus · Seguimiento Comercial del Vendedor.
 *
 * Portado del módulo Miracle (SeguimientoComercialService) adaptado a los
 * pedidos B2B de Great Baby. La diferencia conceptual con Miracle es que
 * acá la "venta del vendedor" se mide sobre PedidoCliente.vendedor_id (lo
 * que el vendedor levantó en terreno), no sobre cotizaciones.
 *
 * Estados de pedido en GB para efectos del tablero:
 *   • retenido   → el semáforo cartera lo bloqueó
 *   • enviado    → esperando aprobación de Gerencia
 *   • aprobado   → listo para facturar
 *   • facturado  → factura emitida (ESTA es la venta real del vendedor)
 *   • despachado → ya salió al cliente
 *   • rechazado  → gerencia dijo que no
 *
 * El "monto vendido" se calcula sobre pedidos facturado + despachado, que
 * son los que efectivamente generaron ingreso. Enviado/aprobado son el
 * pipeline (pedidos abiertos que todavía pueden caerse).
 */
class SeguimientoVendedorService
{
    /** Estados que cuentan como venta efectiva del vendedor. */
    protected array $estadosVenta = ['facturado', 'despachado'];

    /** Estados que cuentan como pipeline (en curso). */
    protected array $estadosPipeline = ['enviado', 'retenido', 'aprobado'];

    /**
     * Resumen del vendedor: monto vendido, cantidad de pedidos y ticket
     * promedio en el periodo.
     *
     * Pasar $vendedorId = null para agregar todos los vendedores (vista
     * admin). El panel del vendedor siempre pasa Auth::id().
     */
    public function resumenVendedor(?int $vendedorId, ?Carbon $fechaInicio = null, ?Carbon $fechaFin = null): array
    {
        $fechaInicio = $fechaInicio ?? Carbon::now()->startOfMonth();
        $fechaFin = $fechaFin ?? Carbon::now()->endOfMonth();

        $row = PedidoCliente::query()
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->whereIn('estado', $this->estadosVenta)
            ->whereBetween('facturado_at', [$fechaInicio, $fechaFin])
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->first();

        $totalVentas = (float) ($row->monto ?? 0);
        $totalPedidos = (int) ($row->cantidad ?? 0);

        return [
            'total_ventas' => $totalVentas,
            'total_pedidos' => $totalPedidos,
            'ticket_promedio' => $totalPedidos > 0 ? $totalVentas / $totalPedidos : 0,
            'periodo' => [
                'inicio' => $fechaInicio->format('Y-m-d'),
                'fin' => $fechaFin->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Comparativa del vendedor entre el periodo actual y el anterior.
     * Portado tal cual de Miracle · incluye tendencia up/down para los chips.
     */
    public function comparativaVendedor(
        ?int $vendedorId,
        Carbon $inicioActual,
        Carbon $finActual,
        Carbon $inicioAnterior,
        Carbon $finAnterior
    ): array {
        $actual = $this->resumenVendedor($vendedorId, $inicioActual, $finActual);
        $anterior = $this->resumenVendedor($vendedorId, $inicioAnterior, $finAnterior);

        $variacion = function ($hoy, $antes) {
            if ($antes > 0) return round((($hoy - $antes) / $antes) * 100, 1);
            return $hoy > 0 ? 100 : 0;
        };

        $varVentas = $variacion($actual['total_ventas'], $anterior['total_ventas']);
        $varPedidos = $variacion($actual['total_pedidos'], $anterior['total_pedidos']);
        $varTicket = $variacion($actual['ticket_promedio'], $anterior['ticket_promedio']);

        return [
            'actual' => $actual,
            'anterior' => $anterior,
            'variacion' => [
                'ventas' => ['valor' => $varVentas, 'tendencia' => $varVentas >= 0 ? 'up' : 'down'],
                'pedidos' => ['valor' => $varPedidos, 'tendencia' => $varPedidos >= 0 ? 'up' : 'down'],
                'ticket' => ['valor' => $varTicket, 'tendencia' => $varTicket >= 0 ? 'up' : 'down'],
            ],
        ];
    }

    /**
     * Desglose de los pedidos del periodo por estado (equivalente al
     * cotizacionesPorEstado de Miracle, adaptado al funnel de GB).
     * Útil para la dona del dashboard.
     */
    public function pedidosPorEstado(?int $vendedorId, ?Carbon $fechaInicio = null, ?Carbon $fechaFin = null): array
    {
        $fechaInicio = $fechaInicio ?? Carbon::now()->startOfMonth();
        $fechaFin = $fechaFin ?? Carbon::now()->endOfMonth();

        $rows = PedidoCliente::query()
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->whereBetween('created_at', [$fechaInicio, $fechaFin])
            ->groupBy('estado')
            ->selectRaw('estado, COUNT(*) as cantidad, COALESCE(SUM(total), 0) as monto')
            ->get()
            ->keyBy('estado');

        // Tasa de conversión: facturados / creados en el periodo.
        $creados = (int) $rows->sum('cantidad');
        $facturados = (int) ($rows->get('facturado')?->cantidad ?? 0) + (int) ($rows->get('despachado')?->cantidad ?? 0);

        return [
            'conteos' => $rows->map(fn ($r) => [
                'cantidad' => (int) $r->cantidad,
                'monto' => (float) $r->monto,
            ])->all(),
            'tasa_conversion' => $creados > 0 ? round(($facturados / $creados) * 100, 1) : 0,
            'creados' => $creados,
        ];
    }

    /**
     * Ranking de clientes del vendedor por monto vendido en el periodo.
     * Portado del Miracle::rankingClientes.
     */
    public function rankingClientes(?int $vendedorId, ?Carbon $fechaInicio = null, ?Carbon $fechaFin = null, int $top = 10): array
    {
        $fechaInicio = $fechaInicio ?? Carbon::now()->startOfMonth();
        $fechaFin = $fechaFin ?? Carbon::now()->endOfMonth();

        return PedidoCliente::query()
            ->when($vendedorId, fn ($q) => $q->where('pedidos_cliente.vendedor_id', $vendedorId))
            ->whereIn('pedidos_cliente.estado', $this->estadosVenta)
            ->whereBetween('pedidos_cliente.facturado_at', [$fechaInicio, $fechaFin])
            ->join('contactos', 'contactos.id', '=', 'pedidos_cliente.contacto_id')
            ->groupBy('contactos.id', 'contactos.razon_social', 'contactos.nombre_completo', 'contactos.ciudad')
            ->selectRaw('contactos.id as contacto_id,
                         COALESCE(contactos.razon_social, contactos.nombre_completo) as cliente,
                         contactos.ciudad,
                         COUNT(*) as pedidos,
                         SUM(pedidos_cliente.total) as monto,
                         MAX(pedidos_cliente.facturado_at) as ultima_compra')
            ->orderByDesc('monto')
            ->limit($top)
            ->get()
            ->map(fn ($r) => [
                'contacto_id' => (int) $r->contacto_id,
                'cliente' => $r->cliente,
                'ciudad' => $r->ciudad,
                'pedidos' => (int) $r->pedidos,
                'monto' => (float) $r->monto,
                'ultima_compra' => $r->ultima_compra,
            ])
            ->all();
    }

    /**
     * Tendencia diaria de los últimos N días (serie para el chart del
     * dashboard). Portado directo de Miracle.
     */
    public function tendenciaDiaria(?int $vendedorId, int $dias = 30): array
    {
        $hasta = Carbon::now()->endOfDay();
        $desde = Carbon::now()->subDays($dias - 1)->startOfDay();

        $rows = PedidoCliente::query()
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->whereIn('estado', $this->estadosVenta)
            ->whereBetween('facturado_at', [$desde, $hasta])
            ->selectRaw('DATE(facturado_at) as dia, COUNT(*) as pedidos, SUM(total) as monto')
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->keyBy('dia');

        $serie = [];
        for ($i = 0; $i < $dias; $i++) {
            $d = $desde->copy()->addDays($i)->format('Y-m-d');
            $r = $rows->get($d);
            $serie[] = [
                'dia' => $d,
                'pedidos' => (int) ($r->pedidos ?? 0),
                'monto' => (float) ($r->monto ?? 0),
            ];
        }
        return $serie;
    }

    /**
     * Seguimiento de pedidos del vendedor · 3 grupos:
     *   - PENDIENTES: aún no facturado (enviado/retenido/aprobado) → qué queda por cerrar
     *   - POR COBRAR: facturado pero saldo > 0 (la factura asociada aún tiene deuda)
     *   - ÚLTIMOS: 20 más recientes del vendedor sin filtro
     * Portado del Miracle::seguimientoPedidos, adaptado a GB.
     */
    public function seguimientoPedidos(?int $vendedorId, int $topUltimos = 20): array
    {
        $base = PedidoCliente::query()
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->with(['contacto:id,razon_social,nombre_completo', 'factura:id,numero,saldo,total']);

        $pendientes = (clone $base)
            ->whereIn('estado', $this->estadosPipeline)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => $this->mapPedidoCard($p))
            ->values()
            ->all();

        $porCobrar = (clone $base)
            ->whereIn('estado', ['facturado', 'despachado'])
            ->whereHas('factura', fn ($q) => $q->where('saldo', '>', 0))
            ->orderByDesc('facturado_at')
            ->limit(50)
            ->get()
            ->map(fn ($p) => $this->mapPedidoCard($p))
            ->values()
            ->all();

        $ultimos = (clone $base)
            ->orderByDesc('created_at')
            ->limit($topUltimos)
            ->get()
            ->map(fn ($p) => $this->mapPedidoCard($p))
            ->values()
            ->all();

        return [
            'pendientes' => $pendientes,
            'por_cobrar' => $porCobrar,
            'ultimos' => $ultimos,
            'totales' => [
                'pendientes' => count($pendientes),
                'por_cobrar' => array_sum(array_column($porCobrar, 'saldo_factura')),
                'ultimos' => count($ultimos),
            ],
        ];
    }

    private function mapPedidoCard(PedidoCliente $p): array
    {
        return [
            'id' => $p->id,
            'numero' => $p->numero,
            'cliente' => $p->contacto?->razon_social ?: $p->contacto?->nombre_completo,
            'estado' => $p->estado,
            'total' => (float) $p->total,
            'fecha' => $p->created_at?->format('Y-m-d H:i'),
            'facturado_at' => $p->facturado_at?->format('Y-m-d'),
            'factura' => $p->factura?->numero,
            'saldo_factura' => (float) ($p->factura?->saldo ?? 0),
            'motivo_retencion' => $p->motivo_retencion,
        ];
    }

    /**
     * Resuelve rango de fechas desde el request. Soporta periodo=mes-actual,
     * mes-anterior, trimestre-actual, anio-actual, y rango custom.
     * Devuelve también las fechas del período anterior para la comparativa.
     */
    public function resolverPeriodo(?string $periodo, ?string $desde = null, ?string $hasta = null): array
    {
        $periodo = $periodo ?: 'mes-actual';
        $hoy = Carbon::now();

        [$inicio, $fin] = match ($periodo) {
            'mes-anterior' => [$hoy->copy()->subMonth()->startOfMonth(), $hoy->copy()->subMonth()->endOfMonth()],
            'trimestre-actual' => [$hoy->copy()->startOfQuarter(), $hoy->copy()->endOfQuarter()],
            'anio-actual' => [$hoy->copy()->startOfYear(), $hoy->copy()->endOfYear()],
            'rango' => [
                Carbon::parse($desde ?? $hoy->copy()->startOfMonth())->startOfDay(),
                Carbon::parse($hasta ?? $hoy)->endOfDay(),
            ],
            default => [$hoy->copy()->startOfMonth(), $hoy->copy()->endOfMonth()],
        };

        $diasEnPeriodo = $inicio->diffInDays($fin) + 1;
        $inicioAnterior = $inicio->copy()->subDays($diasEnPeriodo);
        $finAnterior = $inicio->copy()->subSecond();

        return [$periodo, $inicio, $fin, $inicioAnterior, $finAnterior];
    }
}
