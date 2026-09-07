<?php

namespace App\Modules\Dropi\DTOs;

final class ItemDropiDTO
{
    public function __construct(
        public string $skuDropi,
        public int $cantidad,
        public float $precioProveedorUnit,
    ) {}
}
