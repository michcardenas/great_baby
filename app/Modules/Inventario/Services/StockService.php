<?php

namespace App\Modules\Inventario\Services;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Models\ReservaInventario;
use Illuminate\Support\Collection;

/**
 * Fuente única de verdad para saldos de stock:
 *   saldo_fisico(variante, ubicacion) = SUM(cantidad) del kardex
 *   saldo_reservado(variante, ubicacion) = SUM(cantidad) de reservas activas no expiradas
 *   saldo_disponible = saldo_fisico - saldo_reservado
 */
class StockService
{
    public function saldoFisico(int $varianteId, ?int $ubicacionId = null): int
    {
        $q = InventarioMovimiento::query()->where('variante_id', $varianteId);
        if ($ubicacionId) {
            $q->where('ubicacion_id', $ubicacionId);
        }

        return (int) $q->sum('cantidad');
    }

    public function saldoReservado(int $varianteId, ?int $ubicacionId = null): int
    {
        $q = ReservaInventario::query()
            ->where('variante_id', $varianteId)
            ->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('expira_at')->orWhere('expira_at', '>', now());
            });
        if ($ubicacionId) {
            $q->where('ubicacion_id', $ubicacionId);
        }

        return (int) $q->sum('cantidad');
    }

    public function saldoDisponible(int $varianteId, ?int $ubicacionId = null): int
    {
        return $this->saldoFisico($varianteId, $ubicacionId)
             - $this->saldoReservado($varianteId, $ubicacionId);
    }

    /**
     * Saldo físico agrupado por (variante, ubicacion) para un set de variantes.
     * Devuelve una colección keyed por "variante_id-ubicacion_id".
     */
    public function saldosMasivos(array $varianteIds): Collection
    {
        return InventarioMovimiento::query()
            ->whereIn('variante_id', $varianteIds)
            ->selectRaw('variante_id, ubicacion_id, SUM(cantidad) as saldo')
            ->groupBy('variante_id', 'ubicacion_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->variante_id}-{$row->ubicacion_id}");
    }
}
