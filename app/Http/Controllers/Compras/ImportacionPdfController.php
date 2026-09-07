<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Models\Importacion;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ImportacionPdfController extends Controller
{
    public function pdf(Importacion $importacion): Response
    {
        abort_unless(auth()->check(), 401);

        $importacion->load(['ordenes.items', 'gastos.proveedor', 'lineas.producto', 'lineas.variante', 'liquidador']);

        return Pdf::loadView('compras.pdf.importacion', ['importacion' => $importacion])
            ->setPaper('letter', 'landscape')
            ->stream("IMP-{$importacion->numero}.pdf");
    }
}
