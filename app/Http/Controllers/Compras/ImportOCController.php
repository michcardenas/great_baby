<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SoloAracely;
use App\Modules\Compras\Services\ImportadorOrdenesCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Re-audit M2 R3 PATRÓN R (SEG-A2) · SoloAracely a nivel controller
 *   (defensa en profundidad). Antes solo `abort_unless(auth()->check())` —
 *   si mañana la ruta se movía fuera del grupo `SoloAracely` en `web.php`,
 *   cualquier autenticado podía importar OCs a nombre de proveedores
 *   arbitrarios (auto-cotización, fraude de precios).
 */
class ImportOCController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(SoloAracely::class),
        ];
    }

    public function importar(Request $request, ImportadorOrdenesCompra $importer): RedirectResponse
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls,csv|max:5120',
        ]);

        $ruta = $request->file('archivo')->getRealPath();
        $resultado = $importer->importar($ruta);

        session()->flash('compras_import', $resultado);

        $msg = "{$resultado['ok']} OC creadas";
        if (! empty($resultado['errores'])) {
            $msg .= " · " . count($resultado['errores']) . " errores";
        }

        return redirect()->to('/admin/ordenes-compra')->with('mensaje', $msg);
    }
}
