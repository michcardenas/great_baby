<?php

namespace App\Modules\Dropi\Events;

use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PedidoDespachado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public DropiPedido $pedido) {}

    public function broadcastOn(): array
    {
        // PrivateChannel exige auth via routes/channels.php — antes eran públicos y filtraban montos
        return [
            new PrivateChannel('dropi'),
            new PrivateChannel('dropi.corte.' . $this->pedido->corte_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'PedidoDespachado';
    }

    public function broadcastWith(): array
    {
        return [
            'guia' => $this->pedido->guia,
            'cliente' => $this->pedido->cliente_nombre,
            'ciudad' => $this->pedido->cliente_ciudad,
            'transportadora' => $this->pedido->transportadora,
            'monto' => (float) $this->pedido->monto_esperado_proveedor,
            'despachado_at' => $this->pedido->despachado_at?->toIso8601String(),
        ];
    }
}
