<?php

namespace App\Modules\Dropi\DTOs;

use Carbon\CarbonImmutable;

/**
 * §17, §18 Diseño Dropi — movimientos que caen a la wallet Dropi.
 * Puede ser pago de guía, retiro, indemnización, flete garantía, tarjeta, otro.
 */
final class PagoWalletDTO
{
    public function __construct(
        public string $tipo,
        public float $monto,
        public CarbonImmutable $fecha,
        public ?string $guia = null,
        public ?string $categoria = null,
        public array $raw = [],
        public ?string $dropiMovimientoId = null,
    ) {}
}
