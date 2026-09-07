<?php

namespace App\Http\Controllers\Cartera;

use App\Http\Controllers\Controller;
use App\Models\Contacto;
use App\Modules\Cartera\Actions\CalcularAntiguedadCartera;
use App\Modules\Cartera\Actions\ConsultarCredito;
use App\Modules\Cartera\Enums\EstadoFactura;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class EstadoCuentaController extends Controller
{
    public function pdf(Contacto $contacto): Response
    {
        $u = auth()->user();
        abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Gerente', 'Contador', 'Vendedor'])), 403,
            'Solo roles financieros pueden descargar estados de cuenta.');
        abort_unless($contacto->es_cliente_b2b || $contacto->es_cliente, 403);

        $facturas = $contacto->facturas()
            ->whereNotIn('estado', [EstadoFactura::Anulada])
            ->orderBy('fecha_emision')
            ->get();
        $credito = ConsultarCredito::run($contacto->id);
        $antig = CalcularAntiguedadCartera::run($contacto->id);

        $pdf = Pdf::loadView('cartera.pdf.estado-cuenta', [
            'contacto' => $contacto,
            'facturas' => $facturas,
            'credito' => $credito,
            'antig' => $antig,
        ])->setPaper('letter', 'portrait');

        return $pdf->stream('estado-cuenta-' . $contacto->numero_documento . '.pdf');
    }
}
