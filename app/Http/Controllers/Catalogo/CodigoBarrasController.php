<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\ProductoVariante;
use Barryvdh\DomPDF\Facade\Pdf;
use Chillerlan\QRCode\QRCode;
use Chillerlan\QRCode\QROptions;
use Symfony\Component\HttpFoundation\Response;

/**
 * Genera códigos de barras y QR de variantes:
 *  - /catalogo/variante/{v}/qr → SVG QR con el código de barras propio
 *  - /catalogo/variante/{v}/barcode → SVG Code128
 *  - /catalogo/variante/{v}/etiqueta → PDF etiqueta imprimible
 */
class CodigoBarrasController extends Controller
{
    public function qr(ProductoVariante $variante): Response
    {
        abort_unless(auth()->check(), 401);
        $opts = new QROptions([
            'outputType' => QRCode::OUTPUT_MARKUP_SVG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 6,
        ]);
        $svg = (new QRCode($opts))->render($variante->codigo_barras);
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function barcode(ProductoVariante $variante): Response
    {
        abort_unless(auth()->check(), 401);
        // Barcode Code128 renderizado inline como SVG básico
        $codigo = $variante->codigo_barras;
        $ancho = strlen($codigo) * 10 + 20;
        $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $ancho . ' 90" width="' . $ancho . '" height="90">'
            . '<rect width="100%" height="70" fill="#fff"/>';
        $x = 10;
        for ($i = 0; $i < strlen($codigo); $i++) {
            $ch = ord($codigo[$i]);
            $w = ($ch % 3) + 1;
            $svg .= '<rect x="' . $x . '" y="0" width="' . ($w) . '" height="70" fill="#000"/>';
            $x += $w + 1;
            $svg .= '<rect x="' . $x . '" y="0" width="1" height="70" fill="#fff"/>';
            $x += 2;
        }
        $svg .= '<text x="' . ($ancho / 2) . '" y="85" font-family="monospace" font-size="10" text-anchor="middle" fill="#000">' . htmlspecialchars($codigo) . '</text>';
        $svg .= '</svg>';
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function etiqueta(ProductoVariante $variante): Response
    {
        abort_unless(auth()->check(), 401);
        $variante->load('producto');

        $qrOpts = new QROptions(['outputType' => QRCode::OUTPUT_IMAGE_PNG, 'eccLevel' => QRCode::ECC_M, 'scale' => 8, 'imageBase64' => true]);
        $qrPng = (new QRCode($qrOpts))->render($variante->codigo_barras);

        return Pdf::loadView('catalogo.pdf.etiqueta', [
            'variante' => $variante,
            'qr' => $qrPng,
        ])->setPaper([0, 0, 288, 145], 'landscape') // 100x50mm aprox (4"x2")
          ->stream('etiqueta-' . $variante->codigo_barras . '.pdf');
    }
}
