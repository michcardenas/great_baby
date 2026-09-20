<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Services\MapearOrdenesDropiExcel;
use App\Modules\Dropi\Services\MapearProductosDropiExcel;
use Lorisleiva\Actions\Concerns\AsAction;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Importa un export de Dropi de Mis Pedidos hacia dropi_pedidos, reutilizando
 * SincronizarPedidosDropi (upsert por guía + máquina de estados + bitácora).
 * Puente manual mientras se habilita el MCP/API de Dropi.
 *
 * AUTO-DETECTA el tipo de export por sus columnas:
 *   - "Órdenes con Productos (un producto por fila)" (trae PRODUCTO ID + CANTIDAD)
 *     → arma pedidos CON ítems (agrupa por orden).
 *   - "Órdenes (una orden por fila)" → pedidos a nivel cabecera (sin ítems).
 * Se pueden subir ambos: el segundo re-corre por guía y completa lo que falte.
 */
class ImportarPedidosDropiExcel
{
    use AsAction;

    public function __construct(
        protected MapearOrdenesDropiExcel $mapperOrdenes,
        protected MapearProductosDropiExcel $mapperProductos,
        protected SincronizarPedidosDropi $sync,
    ) {}

    /**
     * @return array{total:int, nuevos:int, actualizados:int, rechazados:int, errores:int, con_items:bool}
     */
    public function handle(string $ruta): array
    {
        $conItems = $this->tieneColumnasDeProducto($ruta);

        $dtos = $conItems
            ? $this->mapperProductos->desdeArchivo($ruta)
            : $this->mapperOrdenes->desdeArchivo($ruta);

        $res = $this->sync->importarPedidos($dtos);
        $res['con_items'] = $conItems;

        return $res;
    }

    /** ¿El export es el de "Órdenes con Productos"? (trae detalle de línea). */
    private function tieneColumnasDeProducto(string $ruta): bool
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $reader = $ext === 'csv' ? new CsvReader() : new XlsxReader();
        $reader->open($ruta);

        $header = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $header = array_map(
                    fn ($c) => mb_strtoupper(trim((string) $c->getValue())),
                    $row->getCells()
                );
                break;
            }
            break;
        }
        $reader->close();

        return in_array('PRODUCTO ID', $header, true) && in_array('CANTIDAD', $header, true);
    }
}
