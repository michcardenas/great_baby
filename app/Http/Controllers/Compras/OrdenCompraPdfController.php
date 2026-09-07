<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Models\OrdenCompra;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OrdenCompraPdfController extends Controller
{
    public function pdf(OrdenCompra $orden): Response
    {
        abort_unless(auth()->check(), 401);

        $orden->load(['items.producto', 'items.variante', 'proveedor', 'bodega', 'creador']);

        return Pdf::loadView('compras.pdf.orden-compra', ['orden' => $orden])
            ->setPaper('letter', 'portrait')
            ->stream("OC-{$orden->numero}.pdf");
    }
}
