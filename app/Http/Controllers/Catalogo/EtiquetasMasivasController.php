<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Barryvdh\DomPDF\Facade\Pdf;
use Chillerlan\QRCode\QRCode;
use Chillerlan\QRCode\QROptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EtiquetasMasivasController extends Controller
{
    public function pdf(Request $request): Response
    {
        abort_unless(auth()->check(), 401);

        $productoIds = (array) $request->input('productos', []);
        $variantes = ProductoVariante::query()
            ->whereIn('producto_id', $productoIds)
            ->with('producto')
            ->orderBy('producto_id')
            ->orderBy('codigo_barras')
            ->get();

        if ($variantes->isEmpty()) {
            abort(404, 'Sin variantes para imprimir.');
        }

        $qrOpts = new QROptions([
            'outputType' => QRCode::OUTPUT_IMAGE_PNG,
            'eccLevel' => QRCode::ECC_M,
            'scale' => 8,
            'imageBase64' => true,
        ]);
        $qrCache = [];
        foreach ($variantes as $v) {
            $qrCache[$v->id] = (new QRCode($qrOpts))->render($v->codigo_barras);
        }

        return Pdf::loadView('catalogo.pdf.etiquetas-lote', [
            'variantes' => $variantes,
            'qrs' => $qrCache,
        ])->setPaper('letter', 'portrait')
          ->stream('etiquetas-lote-' . now()->format('Ymd-His') . '.pdf');
    }
}
