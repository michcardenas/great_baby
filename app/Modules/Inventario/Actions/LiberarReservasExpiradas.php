<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\ReservaInventario;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Libera reservas cuyo `expira_at` ya pasó. Debe correr cada 5 min por cron.
 *
 * Re-audit M3 PATRÓN η (DATOS-C7) · iterar por instancia para disparar
 *   Auditable. Antes bulk `update()` cerraba reservas sin dejar rastro.
 */
class LiberarReservasExpiradas
{
    use AsAction;

    public function handle(): int
    {
        return DB::transaction(function () {
            $reservas = ReservaInventario::query()
                ->where('activa', true)
                ->whereNotNull('expira_at')
                ->where('expira_at', '<', now())
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
