<?php

namespace App\Modules\Crm\Actions;

use App\Models\Contacto;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Segmenta a cada contacto (cliente) según:
 * - Total comprado YTD
 * - Ticket promedio
 * - Días desde última compra
 * - Tasa de mora
 *
 * Segmentos:
 *   VIP        → top 20% por total comprado + ticket alto + baja mora
 *   Frecuente  → compra en los últimos 30 días
 *   Nuevo      → primera compra en últimos 60 días
 *   Dormido    → sin compras hace 90-180 días
 *   Inactivo   → sin compras hace > 180 días
 *   En riesgo  → mora > 60 días acumulada
 */
class SegmentarClientes
{
    use AsAction;

    public function handle(?int $contactoId = null): int
    {
        $procesados = 0;
        $hoy = today();

        $clientes = Contacto::query()
            ->when($contactoId, fn ($q) => $q->where('id', $contactoId))
            ->where(fn ($q) => $q->where('es_cliente', true)->orWhere('es_cliente_b2b', true))
            ->get();

        if ($clientes->isEmpty()) {
            return 0;
        }

        // 1. Cargar métricas de todos en 1 query
        $inicioAnio = $hoy->copy()->startOfYear();
        $metricas = FacturaVenta::query()
            ->whereIn('contacto_id', $clientes->pluck('id'))
            ->whereBetween('fecha_emision', [$inicioAnio, $hoy])
            ->whereNotIn('estado', [EstadoFactura::Anulada])
            ->select('contacto_id', DB::raw('SUM(total) as ytd'),
                DB::raw('AVG(total) as ticket'),
                DB::raw('MAX(fecha_emision) as ultima'),
                DB::raw('COUNT(*) as cantidad'))
            ->groupBy('contacto_id')
            ->get()->keyBy('contacto_id');

        // 2. Mora acumulada (facturas vencidas > 60 días)
        $conMora = FacturaVenta::query()
            ->whereIn('contacto_id', $clientes->pluck('id'))
            ->whereIn('estado', [EstadoFactura::Vencida])
            ->where('fecha_vencimiento', '<', $hoy->copy()->subDays(60))
            ->pluck('contacto_id')->unique()->flip();

        // 3. Top 20% por YTD (umbral VIP)
        $ytds = $metricas->pluck('ytd')->sort()->values();
        $umbralVip = $ytds->count() > 0 ? $ytds->get((int) ($ytds->count() * 0.8)) : PHP_INT_MAX;

        // 4. Asignar segmento por cliente
        foreach ($clientes as $c) {
            $m = $metricas->get($c->id);
            $ytd = (float) ($m->ytd ?? 0);
            $ticket = (float) ($m->ticket ?? 0);
            $ultima = $m->ultima ? \Illuminate\Support\Carbon::parse($m->ultima) : null;
            $diasSin = $ultima ? $hoy->diffInDays($ultima) : PHP_INT_MAX;
            $enMora = $conMora->has($c->id);

            $segmento = match (true) {
                $enMora => 'en_riesgo',
                $ytd >= $umbralVip && $ticket > 0 => 'vip',
                $diasSin <= 30 => 'frecuente',
                $diasSin <= 60 && ($m->cantidad ?? 0) <= 2 => 'nuevo',
                $diasSin > 180 => 'inactivo',
                $diasSin > 90 => 'dormido',
                default => 'frecuente',
            };

            $score = min(100, round(
                ($ytd / max($umbralVip, 1)) * 50    // 50 pts por YTD
                + max(0, 30 - ($diasSin / 6))       // 30 pts por recencia
                + ($enMora ? -20 : 20),             // 20 pts por buen crédito
            2));

            $c->fill([
                'segmento' => $segmento,
                'segmento_score' => $score,
                'ticket_promedio' => $ticket,
                'ultima_compra_at' => $ultima,
                'total_comprado_ytd' => $ytd,
                'segmentado_at' => now(),
            ])->save();
            $procesados++;
        }

        return $procesados;
    }
}
