<?php

namespace App\Modules\Dropi\Jobs;

use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Services\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F14 · Idempotencia + opt-in Habeas Data.
 *
 *   1) Idempotencia: si `notificado_despacho_at` ya está seteado, NO reenvía.
 *      Antes: dos dispatches del despacho generaban dos WhatsApp al cliente.
 *   2) Opt-in Habeas Data: si el Contacto asociado al teléfono tiene
 *      opt_out_marketing, NO se envía (por respeto a la solicitud del cliente).
 *      No confundir con notificaciones transaccionales — el despacho es
 *      transaccional, pero un cliente que pidió ser removido no debería
 *      recibir el mensaje.
 */
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

        // Guard idempotencia — si ya se notificó, salir.
        if ($pedido->notificado_despacho_at) {
            return;
        }

        // Opt-in Habeas Data: si el teléfono del cliente coincide con un
        // Contacto marcado opt_out_marketing, no enviamos.
        try {
            if (class_exists(\App\Models\Contacto::class)) {
                $bloqueado = \App\Models\Contacto::query()
                    ->where('telefono', $pedido->cliente_telefono)
                    ->where('opt_out_marketing', true)
                    ->exists();
                if ($bloqueado) {
                    Log::info('WhatsApp despacho suprimido por opt-out', ['pedido_id' => $pedido->id]);
                    return;
                }
            }
        } catch (\Throwable) {
            // Si la columna no existe todavía, no bloqueamos el envío.
        }

        DB::transaction(function () use ($pedido, $wa) {
            // Marca ANTES de enviar: si el envío falla y el job reintenta,
            // no se duplica. El campo se rollback si la transacción falla.
            $pedido->forceFill(['notificado_despacho_at' => now()])->save();
            $wa->notificarDespacho($pedido);
        });
    }
}
