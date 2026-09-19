<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Services\MapearOrdenesDropiExcel;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Importa el export "Órdenes (una orden por fila)" de Dropi hacia dropi_pedidos,
 * reutilizando SincronizarPedidosDropi (upsert por guía + máquina de estados +
 * bitácora). Puente manual por Excel mientras se habilita el MCP/API de Dropi.
 */
class ImportarPedidosDropiExcel
{
    use AsAction;

    public function __construct(
        protected MapearOrdenesDropiExcel $mapper,
        protected SincronizarPedidosDropi $sync,
    ) {}

    /**
     * @return array{total:int, nuevos:int, actualizados:int, rechazados:int, errores:int}
     */
    public function handle(string $ruta): array
    {
        return $this->sync->importarPedidos($this->mapper->desdeArchivo($ruta));
    }
}
