<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\DropiCorte;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * P5 · El manifiesto contiene PII de compradores finales (dirección, teléfono,
 * cédula). Sólo el equipo administrativo (Aracely/Gerencia) puede descargarlo.
 * El middleware SoloAracely se aplica en routes/web.php.
 *
 * S7 · Guard path traversal: exigimos que la ruta guardada empiece con
 * "manifiestos/" y que el realpath quede bajo storage/app/manifiestos.
 */
class ManifiestoController extends Controller
{
    public function descargar(DropiCorte $corte): BinaryFileResponse
    {
        abort_unless($corte->manifiesto_pdf_path, 404, 'El corte no tiene manifiesto generado.');

        $rel = ltrim((string) $corte->manifiesto_pdf_path, '/\\');
        abort_unless(str_starts_with($rel, 'manifiestos/'), 404, 'Ruta inválida.');

        $rootAbs = realpath(storage_path('app/manifiestos'));
        $fileAbs = realpath(storage_path('app/' . $rel));

        abort_unless($rootAbs && $fileAbs && str_starts_with($fileAbs, $rootAbs . DIRECTORY_SEPARATOR), 404, 'Archivo fuera de manifiestos.');
        abort_unless(file_exists($fileAbs), 404, 'PDF no encontrado en almacenamiento.');

        return response()->file($fileAbs, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="manifiesto-corte-' . $corte->id . '.pdf"',
        ]);
    }
}
