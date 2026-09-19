<?php

namespace App\Modules\Dropi\Services;

use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use Carbon\CarbonImmutable;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Lee el export "Órdenes (una orden por fila)" de Dropi (Mis Pedidos → Acciones)
 * y lo convierte a PedidoDropiDTO, para alimentar SincronizarPedidosDropi::importarPedidos().
 *
 * Mapea por NOMBRE de columna (robusto al orden). Normaliza los ~25 estados de
 * Dropi a los valores canónicos que entiende SincronizarPedidosDropi::mapearEstadoDropi().
 *
 * OJO: el export trae UNA orden por fila SIN detalle de ítems ni SKU, así que los
 * pedidos entran a nivel orden (items vacíos, monto = TOTAL EN PRECIOS DE PROVEEDOR).
 * Las órdenes sin guía (PENDIENTE, aún no despachadas) las rechaza el sync — la guía
 * es la identidad; entran cuando Dropi les asigna guía.
 */
class MapearOrdenesDropiExcel
{
    /** Estatus Dropi (mayúsculas) → valor canónico de EstadoPedidoDropi. Default: despachado. */
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

    /**
     * @return \Generator<PedidoDropiDTO>
     */
    public function desdeArchivo(string $ruta): \Generator
    {
        $reader = $this->crearLector($ruta);
        $reader->open($ruta);

        $col = [];
        $esHeader = true;

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

                $get = fn (string $nombre) => isset($col[$nombre]) ? ($cells[$col[$nombre]] ?? '') : '';

                $guia = strtoupper(trim((string) $get('NÚMERO GUIA')));
                $estatus = mb_strtoupper(trim((string) $get('ESTATUS')));
                $estadoCanonico = self::MAPA_ESTADO[$estatus] ?? 'despachado';

                yield new PedidoDropiDTO(
                    guia: $guia,
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
                    estadoDropi: $estadoCanonico,
                    montoEsperadoProveedor: self::num($get('TOTAL EN PRECIOS DE PROVEEDOR')),
                    montoClienteFinal: self::numOrNull($get('VALOR FACTURADO')),
                    gananciaVendedor: self::numOrNull($get('GANANCIA')),
                    fleteTransportadora: self::numOrNull($get('PRECIO FLETE')),
                    items: [],
                    creadoAt: self::fechaHora($get('FECHA'), $get('HORA')),
                    despachadoAt: null,
                    entregadoAt: null,
                    pagadoAt: null,
                    devueltoAt: null,
                    raw: [
                        'estatus_dropi' => $estatus,
                        'ultimo_movimiento' => $get('ÚLTIMO MOVIMIENTO'),
                        'novedad' => $get('NOVEDAD'),
                        'categorias' => $get('CATEGORÍAS'),
                        'razon_social_fe' => $get('RAZON SOCIAL PARA FACTURACION'),
                        'fe_documento' => $get('FE DOCUMENTO'),
                    ],
                );
            }
            break; // solo la primera hoja
        }
        $reader->close();
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
