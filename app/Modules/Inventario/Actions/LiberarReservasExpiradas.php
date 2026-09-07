<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\ReservaInventario;
use Lorisleiva\Actions\Concerns\AsAction;

class LiberarReservasExpiradas
{
    use AsAction;

    public function handle(): int
    {
        return ReservaInventario::query()
            ->where('activa', true)
            ->whereNotNull('expira_at')
            ->where('expira_at', '<', now())
            ->update(['activa' => false]);
    }
}
