<?php

namespace App\Modules\Dropi\Services;

use App\Modules\Dropi\DTOs\ItemDropiDTO;
use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use Carbon\CarbonImmutable;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee el export "Órdenes con Productos (un producto por fila)" de Dropi
 * (Mis Pedidos → Acciones). Trae la MISMA cabecera de orden que el export
 * "orden por fila" + el detalle de la línea (PRODUCTO/VARIACIÓN/CANTIDAD/
 * PRECIO PROVEEDOR). Agrupa por ID de orden y arma un PedidoDropiDTO CON items,
 * para alimentar SincronizarPedidosDropi::importarPedidos() (mismo flujo que
 * el importador de pedidos; los items se upsert por (pedido, sku_dropi)).
 *
 * La identidad del ítem es el ID de variación de Dropi (único y estable) —
 * el SKU crudo suele venir basura ("'-0"). monto_esperado_proveedor del pedido
 * = suma de PRECIO PROVEEDOR X CANTIDAD de sus líneas.
 */
class MapearProductosDropiExcel
{
    private const MAPA_ESTADO = [
        'ENTREGADO' => 'entregado',
        'CANCELADO' => 'cancelado',
        'RECHAZADO' => 'cancelado',
        'DEVOLUCION' => 'devolucion',
        'EN REEXPEDICION' => 'devolucion',
        'PENDIENTE' => 'pendiente',
        'EN PROCESAMIENTO' => 'pendiente',
        'TELEMERCADEO' => 'pendiente',
    ];

    private const MAX_ORDENES = 50000;

    /**
     * @return \Generator<PedidoDropiDTO>
     */
    public function desdeArchivo(string $ruta): \Generator
    {
        $reader = $this->crearLector($ruta);
        $reader->open($ruta);

        $col = [];
        $esHeader = true;
        /** @var array<string,array{cells:array<int,string>, items:array<int,ItemDropiDTO>, monto:float}> $grupos */
        $grupos = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());

                if ($esHeader) {
                    $esHeader = false;
                    foreach ($cells as $i => $nombre) {
                        $col[mb_strtoupper(trim($nombre))] = $i;
                    }
                    continue;
                }
                if (empty(array_filter($cells))) {
                    continue;
                }

                $get = fn (string $n) => isset($col[$n]) ? ($cells[$col[$n]] ?? '') : '';

                $orderId = trim((string) $get('ID'));
                if ($orderId === '') {
                    continue;
                }

                if (! isset($grupos[$orderId])) {
                    if (count($grupos) >= self::MAX_ORDENES) {
                        break 2;
                    }
                    $grupos[$orderId] = ['cells' => $cells, 'items' => [], 'monto' => 0.0];
                }

                // Ítem de la línea.
                $cant = (int) self::num($get('CANTIDAD'));
                if ($cant > 0) {
                    $sku = self::skuLinea($get('VARIACION ID'), $get('SKU'), $get('PRODUCTO ID'));
                    if ($sku !== null) {
                        $grupos[$orderId]['items'][] = new ItemDropiDTO(
                            skuDropi: $sku,
                            cantidad: $cant,
                            precioProveedorUnit: self::num($get('PRECIO PROVEEDOR')),
                            productoNombre: self::txt($get('PRODUCTO')),
                            variacion: self::txt($get('VARIACION')),
                        );
                        $linea = self::num($get('PRECIO PROVEEDOR X CANTIDAD'));
                        $grupos[$orderId]['monto'] += $linea > 0 ? $linea : self::num($get('PRECIO PROVEEDOR')) * $cant;
                    }
                }
            }
            break; // solo la primera hoja
        }
        $reader->close();

        foreach ($grupos as $g) {
            yield $this->armarPedido($g['cells'], $col, $g['items'], $g['monto']);
        }
    }

    /**
     * @param  array<int,string>  $cells
     * @param  array<string,int>  $col
     * @param  array<int,ItemDropiDTO>  $items
     */
    private function armarPedido(array $cells, array $col, array $items, float $monto): PedidoDropiDTO
    {
        $get = fn (string $n) => isset($col[$n]) ? ($cells[$col[$n]] ?? '') : '';

        $estatus = mb_strtoupper(trim((string) $get('ESTATUS')));

        return new PedidoDropiDTO(
            guia: strtoupper(trim((string) $get('NÚMERO GUIA'))),
            dropiOrdenId: (string) $get('ID'),
            transportadora: self::txt($get('TRANSPORTADORA')),
            tienda: self::txt($get('TIENDA')),
            vendedorNombre: self::txt($get('VENDEDOR')),
            vendedorIdentificacion: null,
            clienteNombre: (string) ($get('NOMBRE CLIENTE') ?: 'SIN NOMBRE'),
            clienteDoc: self::txt($get('NRO DE IDENTIFICACION')),
            clienteTelefono: self::txt($get('TELÉFONO')),
            clienteDireccion: self::txt($get('DIRECCION')),
            clienteCiudad: self::txt($get('CIUDAD DESTINO')),
            clienteDepto: self::txt($get('DEPARTAMENTO DESTINO')),
            estadoDropi: self::MAPA_ESTADO[$estatus] ?? 'despachado',
            montoEsperadoProveedor: $monto,
            montoClienteFinal: self::numOrNull($get('TOTAL DE LA ORDEN')),
            gananciaVendedor: self::numOrNull($get('GANANCIA')),
            fleteTransportadora: self::numOrNull($get('PRECIO FLETE')),
            items: $items,
            creadoAt: self::fechaHora($get('FECHA'), $get('HORA')),
            raw: ['estatus_dropi' => $estatus, 'origen' => 'productsByRow'],
        );
    }

    /** Llave de ítem estable: prioriza el ID de variación de Dropi. */
    private static function skuLinea(string $varId, string $sku, string $prodId): ?string
    {
        $varId = trim($varId);
        if ($varId !== '') {
            return 'DV-' . $varId;
        }
        $sku = trim($sku);
        if ($sku !== '' && $sku !== "'-0" && $sku !== '-0' && $sku !== '-') {
            return $sku;
        }
        $prodId = trim($prodId);
        return $prodId !== '' ? 'DP-' . $prodId : null;
    }

    private function crearLector(string $ruta): XlsxReader|CsvReader
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        return $ext === 'csv' ? new CsvReader() : new XlsxReader();
    }

    private static function txt(string $v): ?string
    {
        $v = trim($v);
        return $v === '' ? null : $v;
    }

    private static function num(string $v): float
    {
        $v = str_replace([',', ' '], ['', ''], trim($v));
        return $v === '' || ! is_numeric($v) ? 0.0 : (float) $v;
    }

    private static function numOrNull(string $v): ?float
    {
        $v = str_replace([',', ' '], ['', ''], trim($v));
        return $v === '' || ! is_numeric($v) ? null : (float) $v;
    }

    private static function fechaHora(string $fecha, string $hora): CarbonImmutable
    {
        $fecha = trim($fecha);
        $hora = trim($hora) ?: '00:00';
        foreach (['d-m-Y H:i', 'Y-m-d H:i', 'd/m/Y H:i'] as $fmt) {
            $c = CarbonImmutable::createFromFormat($fmt, $fecha . ' ' . $hora);
            if ($c !== false) {
                return $c;
            }
        }
        return CarbonImmutable::now();
    }
}
