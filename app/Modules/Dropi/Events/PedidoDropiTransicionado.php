<?php

namespace App\Modules\Dropi\Events;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Se dispara cada vez que un DropiPedido cambia de estado a través del trait
 * HasEstadoDropi::transicionar(). Todo side-effect (inventario, contabilidad,
 * notificaciones) DEBE colgarse de aquí — no reimplementar en Actions ni controllers.
 */
class PedidoDropiTransicionado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public DropiPedido $pedido,
        public ?EstadoPedidoDropi $estadoDesde,
        public EstadoPedidoDropi $estadoHasta,
        public string $fuente,
        public ?int $userId = null,
        public array $payload = [],
    ) {}
}
