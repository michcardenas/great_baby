<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Enums\EstadoOrdenCompra;
use App\Modules\Compras\Models\OrdenCompra;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

class AprobarOrdenCompra
{
    use AsAction;

    public function handle(OrdenCompra $orden): OrdenCompra
    {
        if (! in_array($orden->estado, [EstadoOrdenCompra::Borrador, EstadoOrdenCompra::Enviada], true)) {
            throw new InvalidArgumentException('Sólo se aprueban OC en borrador o enviadas.');
        }
        if ($orden->items()->count() === 0) {
            throw new InvalidArgumentException('La OC no tiene ítems.');
        }

        $orden->estado = EstadoOrdenCompra::Aprobada;
        $orden->aprobado_por = auth()->id();
        $orden->aprobado_at = now();
        $orden->save();

        return $orden;
    }
}
