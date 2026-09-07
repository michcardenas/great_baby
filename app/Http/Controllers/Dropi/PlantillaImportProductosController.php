<?php

namespace App\Http\Controllers\Dropi;

use App\Http\Controllers\Controller;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaImportProductosController extends Controller
{
    public function descargar(): StreamedResponse
    {
        $columnas = [
            'referencia', 'nombre', 'categoria', 'precio_proveedor',
            'requiere_talla', 'es_set', 'activo',
            // Variantes opcionales en la misma fila:
            'color_codigo', 'color_nombre', 'diseno_codigo', 'diseno_nombre', 'talla',
        ];

        $ejemplos = [
            ['AND2512-79/154', 'Body león manga larga', 'ropa', 12500, true, false, true, '02', 'azul', 'LEÓ', 'león', '6M'],
            ['AND2512-79/154', 'Body león manga larga', 'ropa', 12500, true, false, true, '05', 'rosa', 'LEÓ', 'león', '12M'],
            ['BAB4402-11', 'Chupo pico anatómico', 'accesorio', 8900, false, false, true, '01', 'blanco', '', '', ''],
        ];

        return response()->streamDownload(function () use ($columnas, $ejemplos) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($columnas));
            foreach ($ejemplos as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, 'plantilla-productos-greatbaby.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
