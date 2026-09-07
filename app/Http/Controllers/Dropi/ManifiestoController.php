<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\DropiCorte;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManifiestoController extends Controller
{
    public function descargar(DropiCorte $corte): BinaryFileResponse
    {
        abort_unless(auth()->check(), 401);
        abort_unless($corte->manifiesto_pdf_path, 404, 'El corte no tiene manifiesto generado.');

        $abs = storage_path('app/' . $corte->manifiesto_pdf_path);
        abort_unless(file_exists($abs), 404, 'PDF no encontrado en almacenamiento.');

        return response()->file($abs, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="manifiesto-corte-' . $corte->id . '.pdf"',
        ]);
    }
}
