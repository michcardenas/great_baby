<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\ReservaInventario;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Libera todas las reservas activas de un origen (venta cancelada, factura anulada).
 * Retorna cuántas liberó.
 *
 * Re-audit M3 PATRÓN η (DATOS-C7) · iteración por instancia → dispara Auditable
 *   (bulk update NO dispara `updating/updated`). Sin esto, las reservas se
 *   apagaban sin dejar traza DIAN.
 */
class LiberarReserva
{
    use AsAction;

    public function handle(Model $origen): int
    {
        return DB::transaction(function () use ($origen) {
            $reservas = ReservaInventario::query()
                ->where('origen_type', get_class($origen))
                ->where('origen_id', $origen->getKey())
                ->where('activa', true)
                ->lockForUpdate()
                ->get();
            $n = 0;
            foreach ($reservas as $r) {
                $r->activa = false;
                $r->save();
                $n++;
            }
            return $n;
        });
    }
}
