<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Models\ReservaInventario;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reserva stock para un pedido/factura. Valida disponible bajo lock.
 *
 * Re-audit M3 PATRÓN β + θ (SEG-A1 / DATOS-C2):
 *   - Antes: `$cantidad` sin validar → una reserva con cantidad=-999999 pasaba
 *     y como saldoDisponible = fisico - reservado, un reservado NEGATIVO
 *     inflaba el disponible (invento infinito).
 *   - Sin lockForUpdate → dos ventas concurrentes reservaban ambas la última
 *     unidad: saldo real -1.
 *   - Sin rate-limit → loop de reservas de 1u sobre miles de SKUs paralizaba
 *     ventas.
 */
class ReservarStock
{
    use AsAction;

    public function __construct(protected StockService $stock)
    {
    }

    public function handle(
        int $varianteId,
        int $ubicacionId,
        int $cantidad,
        Model $origen,
        ?\DateTimeInterface $expiraAt = null,
    ): ReservaInventario {
        // PATRÓN θ · guard estricto de cantidad.
        if ($cantidad <= 0) {
            throw new InvalidArgumentException("Cantidad de reserva debe ser > 0 (recibido {$cantidad}).");
        }
        if ($cantidad > 10000) {
            throw new InvalidArgumentException("Cantidad de reserva excede el máximo permitido (10.000 u).");
        }
        if ($varianteId <= 0 || $ubicacionId <= 0) {
            throw new InvalidArgumentException('variante_id y ubicacion_id son obligatorios.');
        }

        return DB::transaction(function () use ($varianteId, $ubicacionId, $cantidad, $origen, $expiraAt) {
            // PATRÓN β · lock kardex + reservas de esta variante+ubicación.
            InventarioMovimiento::query()
                ->where('variante_id', $varianteId)
                ->where('ubicacion_id', $ubicacionId)
                ->lockForUpdate()->get();
            ReservaInventario::query()
                ->where('variante_id', $varianteId)
                ->where('ubicacion_id', $ubicacionId)
                ->where('activa', true)
                ->lockForUpdate()->get();

            // Re-audit M3 PATRÓN ν (FUNC-M3) · IDEMPOTENCIA por (origen, variante).
            //   Antes: dos llamadas al mismo pedido creaban dos reservas y
            //   bloqueaban 2× el stock hasta expirar. Ahora: si ya existe
            //   una reserva activa para (origen_type, origen_id, variante,
            //   ubicacion), la actualizamos en lugar de duplicar.
            $existente = ReservaInventario::query()
                ->where('origen_type', get_class($origen))
                ->where('origen_id', $origen->getKey())
                ->where('variante_id', $varianteId)
                ->where('ubicacion_id', $ubicacionId)
                ->where('activa', true)
                ->first();

            if ($existente) {
                // Chequeo del delta contra disponible (no del total).
                $delta = $cantidad - (int) $existente->cantidad;
                if ($delta > 0) {
                    $disp = $this->stock->saldoDisponible($varianteId, $ubicacionId);
                    if ($disp < $delta) {
                        throw new InvalidArgumentException(
                            "Stock insuficiente para ampliar reserva · variante {$varianteId}: disponible {$disp}, delta requerido {$delta}."
                        );
                    }
                }
                $existente->cantidad = $cantidad;
                if ($expiraAt) $existente->expira_at = $expiraAt;
                $existente->save();
                return $existente;
            }

            $disp = $this->stock->saldoDisponible($varianteId, $ubicacionId);
            if ($disp < $cantidad) {
                throw new InvalidArgumentException(
                    "Stock insuficiente · variante {$varianteId} en ubicación {$ubicacionId}: disponible {$disp}, requerido {$cantidad}."
                );
            }

            return ReservaInventario::create([
                'variante_id' => $varianteId,
                'ubicacion_id' => $ubicacionId,
                'cantidad' => $cantidad,
                'origen_type' => get_class($origen),
                'origen_id' => $origen->getKey(),
                'expira_at' => $expiraAt,
                'activa' => true,
                'user_id' => auth()->id(),
            ]);
        });
    }
}
