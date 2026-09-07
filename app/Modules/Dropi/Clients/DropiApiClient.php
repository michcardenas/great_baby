<?php

namespace App\Modules\Dropi\Clients;

use App\Modules\Dropi\DTOs\PedidoDropiDTO;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

/**
 * Driver HTTP real — a completar cuando Dropi entregue credenciales §26.
 * Por ahora lanza excepción explícita si se intenta usar sin config.
 * La lógica de negocio no cambia porque toda la app depende del INTERFACE, no de esta clase.
 */
class DropiApiClient implements DropiClientInterface
{
    public function __construct(
        protected PendingRequest $http,
        protected string $baseUrl,
        protected string $apiKey,
    ) {}

    public function driver(): string
    {
        return 'api';
    }

    public function pedidosDesde(CarbonImmutable $desde): Collection
    {
        $this->assertConfigurado();

        // TODO: implementar cuando Dropi entregue documentación oficial (§26).
        // Endpoint tentativo: GET {baseUrl}/orders?updated_since={iso8601}
        // Con paginación por cursor y rate limiting a respetar.
        throw new \LogicException('DropiApiClient::pedidosDesde() aún no implementado — esperando docs de Dropi.');
    }

    public function consultarGuia(string $guia): ?PedidoDropiDTO
    {
        $this->assertConfigurado();
        throw new \LogicException('DropiApiClient::consultarGuia() aún no implementado.');
    }

    public function walletDesde(CarbonImmutable $desde): Collection
    {
        $this->assertConfigurado();
        throw new \LogicException('DropiApiClient::walletDesde() aún no implementado.');
    }

    protected function assertConfigurado(): void
    {
        if ($this->apiKey === '' || $this->baseUrl === '') {
            throw new \RuntimeException(
                'DropiApiClient sin credenciales. Configure DROPI_API_URL y DROPI_API_KEY en .env, '
                . 'o use DROPI_DRIVER=mock para desarrollo.'
            );
        }
    }
}
