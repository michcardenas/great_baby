<?php

namespace App\Modules\Dropi\DTOs;

use Carbon\CarbonImmutable;

/**
 * Contrato de datos con el que trabajamos internamente,
 * independiente del formato exacto que devuelva la API de Dropi.
 * El client (real o mock) normaliza el payload de Dropi a este DTO.
 */
final class PedidoDropiDTO
{
    /**
     * @param  ItemDropiDTO[]  $items
     */
    public function __construct(
        public string $guia,
        public string $dropiOrdenId,
        public ?string $transportadora,
        public ?string $tienda,
        public ?string $vendedorNombre,
        public ?string $vendedorIdentificacion,
        public string $clienteNombre,
        public ?string $clienteDoc,
        public ?string $clienteTelefono,
        public ?string $clienteDireccion,
        public ?string $clienteCiudad,
        public ?string $clienteDepto,
        public string $estadoDropi,
        public float $montoEsperadoProveedor,
        public ?float $montoClienteFinal,
        public ?float $gananciaVendedor,
        public ?float $fleteTransportadora,
        public array $items,
        public CarbonImmutable $creadoAt,
        public ?CarbonImmutable $despachadoAt = null,
        public ?CarbonImmutable $entregadoAt = null,
        public ?CarbonImmutable $pagadoAt = null,
        public ?CarbonImmutable $devueltoAt = null,
        public array $raw = [],
    ) {}
}
