<?php

namespace App\Modules\Inventario\Services;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Models\ReservaInventario;
use Illuminate\Support\Collection;

/**
 * Fuente única de verdad para saldos de stock.
 *
 * ─── Modo GRANULAR (producto.desglose_stock = true, comportamiento clásico) ───
 *   saldo_fisico(variante, ubicacion) = SUM(cantidad) del kardex por variante
 *   saldo_reservado(variante, ubicacion) = SUM(cantidad) reservas activas
 *   saldo_disponible = saldo_fisico - saldo_reservado
 *
 * ─── Modo AGREGADO (C-F1: producto.desglose_stock = false) ───
 *   saldo_fisico(producto, ubicacion) = SUM(cantidad) del kardex por producto SIN variante
 *   saldo_reservado(producto, ubicacion) = SUM(cantidad) reservas por producto SIN variante
 *   saldo_disponible = saldo_fisico - saldo_reservado
 *
 * Los métodos `*Sujeto()` bifurcan según el tipo del argumento.
 */
class StockService
{
    // ─────────────────────────────────────────────────────────
    // API GRANULAR (variante) — sin cambios de comportamiento vs. versión previa
    // ─────────────────────────────────────────────────────────

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

    /**
     * C-F2 · Análogo de saldosMasivos() pero para productos agregados.
     * Devuelve una colección keyed por "producto_id-ubicacion_id".
     * Solo cuenta movimientos "de producto" (variante_id IS NULL).
     */
    public function saldosMasivosProducto(array $productoIds): Collection
    {
        return InventarioMovimiento::query()
            ->whereIn('producto_id', $productoIds)
            ->whereNull('variante_id')
            ->selectRaw('producto_id, ubicacion_id, SUM(cantidad) as saldo')
            ->groupBy('producto_id', 'ubicacion_id')
            ->get()
            ->keyBy(fn ($row) => "{$row->producto_id}-{$row->ubicacion_id}");
    }

    // ─────────────────────────────────────────────────────────
    // API AGREGADO (producto sin variantes) — C-F2 nuevo
    // ─────────────────────────────────────────────────────────

    /**
     * Saldo físico de un producto agregado (`desglose_stock=false`).
     * Suma solo movimientos donde variante_id IS NULL (movimientos "de producto").
     */
    public function saldoFisicoProducto(int $productoId, ?int $ubicacionId = null): int
    {
        $q = InventarioMovimiento::query()
            ->where('producto_id', $productoId)
            ->whereNull('variante_id');
        if ($ubicacionId) {
            $q->where('ubicacion_id', $ubicacionId);
        }

        return (int) $q->sum('cantidad');
    }

    public function saldoReservadoProducto(int $productoId, ?int $ubicacionId = null): int
    {
        $q = ReservaInventario::query()
            ->where('producto_id', $productoId)
            ->whereNull('variante_id')
            ->where('activa', true)
            ->where(function ($q) {
                $q->whereNull('expira_at')->orWhere('expira_at', '>', now());
            });
        if ($ubicacionId) {
            $q->where('ubicacion_id', $ubicacionId);
        }

        return (int) $q->sum('cantidad');
    }

    public function saldoDisponibleProducto(int $productoId, ?int $ubicacionId = null): int
    {
        return $this->saldoFisicoProducto($productoId, $ubicacionId)
             - $this->saldoReservadoProducto($productoId, $ubicacionId);
    }

    // ─────────────────────────────────────────────────────────
    // API POLIMÓRFICA · el motor decide por el tipo del sujeto
    // ─────────────────────────────────────────────────────────

    /**
     * Saldo físico de un sujeto (Producto agregado o ProductoVariante granular).
     * Es el punto de entrada preferido para código nuevo.
     */
    public function saldoFisicoSujeto(Producto|ProductoVariante $sujeto, ?int $ubicacionId = null): int
    {
        if ($sujeto instanceof ProductoVariante) {
            return $this->saldoFisico($sujeto->id, $ubicacionId);
        }
        // Producto agregado — debe tener desglose_stock=false, sino es error de uso.
        $this->assertProductoAgregado($sujeto);
        return $this->saldoFisicoProducto($sujeto->id, $ubicacionId);
    }

    public function saldoReservadoSujeto(Producto|ProductoVariante $sujeto, ?int $ubicacionId = null): int
    {
        if ($sujeto instanceof ProductoVariante) {
            return $this->saldoReservado($sujeto->id, $ubicacionId);
        }
        $this->assertProductoAgregado($sujeto);
        return $this->saldoReservadoProducto($sujeto->id, $ubicacionId);
    }

    public function saldoDisponibleSujeto(Producto|ProductoVariante $sujeto, ?int $ubicacionId = null): int
    {
        return $this->saldoFisicoSujeto($sujeto, $ubicacionId)
             - $this->saldoReservadoSujeto($sujeto, $ubicacionId);
    }

    /**
     * Guardarraíl · llamar API agregada sobre un producto con desglose=true es un bug de uso:
     * el caller debería iterar sus variantes. Fallamos rápido y ruidoso.
     */
    private function assertProductoAgregado(Producto $p): void
    {
        if ($p->desglose_stock) {
            throw new \DomainException(
                "Producto #{$p->id} ({$p->referencia}) tiene desglose_stock=true. ".
                "Usa saldoFisicoSujeto con una ProductoVariante, o agrega saldos por variante."
            );
        }
    }
}
