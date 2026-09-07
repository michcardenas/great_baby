<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use App\Modules\Compras\Services\ImportadorOrdenesCompra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImportOCController extends Controller
{
    public function importar(Request $request, ImportadorOrdenesCompra $importer): RedirectResponse
    {
        abort_unless(auth()->check(), 401);

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
