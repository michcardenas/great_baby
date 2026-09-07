<?php

namespace App\Http\Controllers\Cartera;

use App\Http\Controllers\Controller;
use App\Models\EmpresaConfig;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Siigo\Services\QrDianService;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class FacturaPdfController extends Controller
{
    public function pdf(FacturaVenta $factura): Response
    {
        $u = auth()->user();
        abort_unless($u && ($u->esAracely() || $u->hasAnyRole(['Gerente', 'Contador'])), 403);
        return $this->render($factura);
    }

    /**
     * Descarga pública por token (no requiere auth).
     * URL de ejemplo: /factura/publica/{token}
     */
    public function publica(string $token): Response
    {
        $factura = FacturaVenta::where('token_publico', $token)->firstOrFail();

        // Facturas anuladas ya no deben poder descargarse por el link viejo.
        if ($factura->estado === \App\Modules\Cartera\Enums\EstadoFactura::Anulada) {
            abort(410, 'Esta factura fue anulada.');
        }

        return $this->render($factura, publica: true);
    }

    private function render(FacturaVenta $factura, bool $publica = false): Response
    {
        $factura->load(['contacto', 'items.variante.producto', 'pagos']);
        $empresa = EmpresaConfig::current();

        $qrSvg = '';
        if ($factura->cufe) {
            $qrSvg = app(QrDianService::class)->generarSvg($factura, 130);
        }

        return Pdf::loadView('cartera.pdf.factura', [
            'factura' => $factura,
            'empresa' => $empresa,
            'qrSvg' => $qrSvg,
            'publica' => $publica,
        ])->setPaper('letter', 'portrait')
          ->stream('factura-' . $factura->numero . '.pdf');
    }
}
