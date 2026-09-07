<?php

namespace App\Http\Controllers\Cartera;

use App\Http\Controllers\Controller;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlantillaContactosController extends Controller
{
    public function descargar(): StreamedResponse
    {
        $columnas = [
            'tipo_documento', 'numero_documento', 'nombre_completo', 'razon_social',
            'email', 'telefono', 'direccion', 'ciudad', 'departamento',
            'es_cliente', 'es_cliente_b2b', 'es_proveedor', 'es_vendedor_dropi', 'es_empleado',
            'cupo', 'plazo_dias',
        ];

        $ejemplos = [
            ['NIT', '900111222', 'Distribuidora Ejemplo SAS', 'DISTRIBUIDORA EJEMPLO SAS', 'ventas@ejemplo.com', '3011234567', 'Calle 45 #12-34', 'Bogotá', 'Cundinamarca', 'true', 'true', 'false', 'false', 'false', 3000000, 30],
            ['NIT', '901333444', 'Textiles Andinos', 'TEXTILES ANDINOS SAS', 'compras@andinos.com', '3151112222', 'Cra 15 #22-45', 'Bogotá', 'Cundinamarca', 'false', 'false', 'true', 'false', 'false', 0, 0],
            ['CC', '1094567890', 'Vendedor Dropshipper', '', 'vendedor@correo.com', '3201234567', 'Barrio Ejemplo', 'Medellín', 'Antioquia', 'false', 'false', 'false', 'true', 'false', 0, 0],
        ];

        return response()->streamDownload(function () use ($columnas, $ejemplos) {
            $writer = new Writer();
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues($columnas));
            foreach ($ejemplos as $row) {
                $writer->addRow(Row::fromValues($row));
            }
            $writer->close();
        }, 'plantilla-contactos-greatbaby.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
