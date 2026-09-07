<?php

namespace App\Modules\Dropi\Jobs;

use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Services\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotificarClienteDespachoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $pedidoId) {}

    public function handle(WhatsAppClient $wa): void
    {
        $pedido = DropiPedido::find($this->pedidoId);
        if (! $pedido || ! $pedido->cliente_telefono) {
            return;
        }
        $wa->notificarDespacho($pedido);
    }
}
