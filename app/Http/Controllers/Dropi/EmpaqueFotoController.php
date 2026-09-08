<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sirve las fotos de empaque desde storage privado (nunca públicas).
 * Solo Aracely y Alistadores pueden verlas.
 */
class EmpaqueFotoController extends Controller
{
    public function ver(EmpaqueRegistro $registro): Response
    {
        $u = auth()->user();
        // Solo Aracely puede ver cualquier foto; un Alistador solo la suya (evita IDOR).
        abort_unless($u && ($u->esAracely() || (int) $registro->operario_id === (int) $u->id), 403);

        abort_unless($registro->foto_path && Storage::disk('local')->exists($registro->foto_path), 404);

        $bin = Storage::disk('local')->get($registro->foto_path);
        $mime = str_ends_with($registro->foto_path, '.png')
            ? 'image/png'
            : (str_ends_with($registro->foto_path, '.webp') ? 'image/webp' : 'image/jpeg');

        return response($bin, 200, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, max-age=3600',
            'Content-Disposition' => 'inline; filename="empaque-' . $registro->id . '.jpg"',
        ]);
    }
}
