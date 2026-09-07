<?php

namespace App\Modules\Dropi\Services;

use App\Modules\Dropi\Models\DropiPedido;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Notificador WhatsApp — driver mock por defecto (log solamente).
 * Cuando llegue el token de WhatsApp Cloud API, se activa el driver `cloud`.
 *
 * Config en .env:
 *   WHATSAPP_DRIVER=mock (default) | cloud
 *   WHATSAPP_TOKEN=EAAxxx...
 *   WHATSAPP_PHONE_ID=1234567890
 *   WHATSAPP_TEMPLATE_DESPACHO=pedido_despachado_v1
 */
class WhatsAppClient
{
    public function notificarDespacho(DropiPedido $pedido): array
    {
        $driver = config('dropi.whatsapp.driver', 'mock');
        $mensaje = $this->componerMensajeDespacho($pedido);

        if ($driver === 'mock') {
            Log::channel('single')->info('[WhatsApp MOCK] Notificación despacho', [
                'guia' => $pedido->guia,
                'telefono' => $pedido->cliente_telefono,
                'mensaje' => $mensaje,
            ]);
            return ['status' => 'mock', 'destino' => $pedido->cliente_telefono, 'mensaje' => $mensaje];
        }

        // Driver real: WhatsApp Cloud API (Meta)
        $token = config('dropi.whatsapp.token');
        $phoneId = config('dropi.whatsapp.phone_id');
        $template = config('dropi.whatsapp.template_despacho');

        if (! $token || ! $phoneId || ! $pedido->cliente_telefono) {
            return ['status' => 'skipped', 'razon' => 'credenciales o teléfono faltantes'];
        }

        $response = Http::withToken($token)
            ->timeout(10)
            ->post("https://graph.facebook.com/v18.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $pedido->cliente_telefono,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'es_CO'],
                    'components' => [
                        ['type' => 'body', 'parameters' => [
                            ['type' => 'text', 'text' => $pedido->cliente_nombre],
                            ['type' => 'text', 'text' => $pedido->guia],
                            ['type' => 'text', 'text' => $pedido->transportadora ?? 'transportadora'],
                        ]],
                    ],
                ],
            ]);

        return [
            'status' => $response->successful() ? 'enviado' : 'error',
            'response' => $response->json(),
        ];
    }

    protected function componerMensajeDespacho(DropiPedido $pedido): string
    {
        return sprintf(
            "Hola %s! Tu pedido #%s salió hoy con %s. Te avisaremos apenas sea entregado. — GREAT BABY",
            $pedido->cliente_nombre,
            $pedido->guia,
            $pedido->transportadora ?? 'la transportadora'
        );
    }

    /**
     * Envío genérico de mensaje de texto libre (usado por CobranzaWhatsApp).
     * Respeta el driver mock/cloud igual que notificarDespacho().
     *
     * @return array{status:string, destino?:string, mensaje?:string, response?:mixed, razon?:string}
     */
    public function enviarTexto(string $telefono, string $mensaje): array
    {
        $driver = config('dropi.whatsapp.driver', 'mock');

        if (empty($telefono)) {
            return ['status' => 'skipped', 'razon' => 'sin telefono'];
        }

        if ($driver === 'mock') {
            Log::channel('single')->info('[WhatsApp MOCK] Texto libre', [
                'destino' => $telefono,
                'mensaje' => $mensaje,
            ]);
            return ['status' => 'mock', 'destino' => $telefono, 'mensaje' => $mensaje];
        }

        $token = config('dropi.whatsapp.token');
        $phoneId = config('dropi.whatsapp.phone_id');
        if (! $token || ! $phoneId) {
            return ['status' => 'skipped', 'razon' => 'credenciales faltantes'];
        }

        $response = Http::withToken($token)
            ->timeout(10)
            ->post("https://graph.facebook.com/v18.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $telefono,
                'type' => 'text',
                'text' => ['body' => $mensaje],
            ]);

        return [
            'status' => $response->successful() ? 'enviado' : 'error',
            'destino' => $telefono,
            'response' => $response->json(),
        ];
    }
}
