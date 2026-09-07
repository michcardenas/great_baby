<?php

namespace App\Modules\Dropi\Clients;

use App\Modules\Dropi\DTOs\PagoWalletDTO;
use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Contrato único con el que el resto del sistema habla con Dropi.
 * Dos implementaciones:
 *   - DropiApiClient: HTTP real contra la API de Dropi (cuando habiliten credenciales §26)
 *   - DropiMockClient: fixtures locales para desarrollo y pruebas
 */
interface DropiClientInterface
{
    /**
     * Traer pedidos nuevos o actualizados desde una fecha/hora (delta sync).
     *
     * @return Collection<int, PedidoDropiDTO>
     */
    public function pedidosDesde(CarbonImmutable $desde): Collection;

    /**
     * Consultar el estado actual de una guía específica.
     */
    public function consultarGuia(string $guia): ?PedidoDropiDTO;

    /**
     * Traer movimientos de la wallet Dropi desde una fecha.
     *
     * @return Collection<int, PagoWalletDTO>
     */
    public function walletDesde(CarbonImmutable $desde): Collection;

    /**
     * Informar cuál driver está activo (útil para banners UI y logs).
     */
    public function driver(): string;
}
