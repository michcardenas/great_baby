<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\ReservaInventario;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;

class LiberarReserva
{
    use AsAction;

    /**
     * Libera todas las reservas activas de un origen (venta cancelada, factura anulada).
     * Retorna cuántas liberó.
     */
    public function handle(Model $origen): int
    {
        return ReservaInventario::query()
            ->where('origen_type', get_class($origen))
            ->where('origen_id', $origen->getKey())
            ->where('activa', true)
            ->update(['activa' => false]);
    }
}
