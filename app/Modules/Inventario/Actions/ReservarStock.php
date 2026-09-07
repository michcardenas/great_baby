<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\ReservaInventario;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Reserva stock para un pedido/factura. Valida disponible.
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
        return DB::transaction(function () use ($varianteId, $ubicacionId, $cantidad, $origen, $expiraAt) {
            $disp = $this->stock->saldoDisponible($varianteId, $ubicacionId);
            if ($disp < $cantidad) {
                throw new InvalidArgumentException(
                    "Stock insuficiente: disponible {$disp}, requerido {$cantidad}."
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
