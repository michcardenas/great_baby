<?php

namespace App\Http\Controllers\Compras;

use App\Http\Controllers\Controller;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaImportOCController extends Controller
{
    public function descargar(): StreamedResponse
    {
        abort_unless(auth()->check(), 401);

        $filename = 'plantilla-ordenes-compra.xlsx';

        return new StreamedResponse(function () {
            $writer = new Writer();
            $writer->openToFile('php://output');

            $writer->addRow(Row::fromValues([
                'numero_oc', 'proveedor_nit', 'tipo', 'moneda', 'tasa_cambio', 'fecha_esperada',
                'producto_referencia', 'variante_codigo', 'descripcion',
                'cantidad', 'precio_unit', 'descuento_pct', 'iva_pct', 'observaciones',
            ]));

            $writer->addRow(Row::fromValues([
                'OC-EJ-001', '900123456', 'nacional', 'COP', 1, '2026-10-15',
                'REF-001', 'REF-001-RS-T2', 'Body bebé rosa T2',
                50, 6000, 0, 19, 'Ejemplo — borrar antes de importar',
            ]));

            $writer->close();
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename={$filename}",
        ]);
    }
}
