<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve las fotos de empaque desde storage privado (nunca públicas).
 * Solo Aracely y Alistadores pueden verlas.
 *
 * F18 · MIME real: detectamos por firma binaria (finfo), no por sufijo del path
 * — así un archivo renombrado con extensión falsa no se sirve con Content-Type
 * incorrecto (bloquea vectores XSS/CSP via image confusion).
 */
class EmpaqueFotoController extends Controller
{
    private const MIMES_PERMITIDOS = ['image/jpeg', 'image/png', 'image/webp'];

    public function ver(EmpaqueRegistro $registro): Response
    {
        $u = auth()->user();
        abort_unless($u && ($u->esAracely() || (int) $registro->operario_id === (int) $u->id), 403);

        abort_unless($registro->foto_path && Storage::disk('local')->exists($registro->foto_path), 404);

        $bin = Storage::disk('local')->get($registro->foto_path);

        // MIME real por firma binaria.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bin) ?: 'application/octet-stream';

        // Whitelist: si no es imagen permitida, no la servimos como tal.
        if (! in_array($mime, self::MIMES_PERMITIDOS, true)) {
            abort(415, 'Archivo con tipo no soportado.');
        }

        $ext = match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        return response($bin, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="empaque-' . $registro->id . '.' . $ext . '"',
        ]);
    }
}
