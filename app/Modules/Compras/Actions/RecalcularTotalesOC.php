<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Compras\Models\OrdenCompra;
use Lorisleiva\Actions\Concerns\AsAction;

class RecalcularTotalesOC
{
    use AsAction;

    public function handle(OrdenCompra $orden): OrdenCompra
    {
        $orden->loadMissing('items');

        $subtotal = round((float) $orden->items->sum('subtotal'), 2);
        $iva = round((float) $orden->items->sum('iva'), 2);

        $retenciones = (float) $orden->retefuente + (float) $orden->reteiva + (float) $orden->reteica;
        $total = round($subtotal + $iva - $retenciones - (float) $orden->descuento, 2);

        $orden->subtotal = $subtotal;
        $orden->iva = $iva;
        $orden->total = $total;
        $orden->save();

        return $orden;
    }
}
