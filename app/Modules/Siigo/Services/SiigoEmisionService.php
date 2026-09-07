<?php

namespace App\Modules\Siigo\Services;

use App\Mail\FacturaClienteMail;
use App\Models\NotificacionErp;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoConfig;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Emite facturas de venta electrónicas a la DIAN vía Siigo API (POST /v1/invoices).
 * Timbra con stamp.send = true.
 *
 * Anti-doble-emisión: setea `emitiendo_at` bajo lock; si ya hay uno < 60s se rechaza.
 * Email + notif salen FUERA de la DB::transaction para no bloquear la conexión con SMTP.
 *
 * @see https://developers.siigo.com/docs/siigoapi/invoice/1-create-invoice
 */
class SiigoEmisionService
{
    public function __construct(
        private readonly SiigoClient $cliente,
        private readonly QrDianService $qr,
    ) {}

    public function emitir(FacturaVenta $factura): FacturaVenta
    {
        $this->validarElegible($factura);
        $config = $this->validarConfig();

        // 1. LOCK anti-carrera + set emitiendo_at
        $factura = DB::transaction(function () use ($factura) {
            $f = FacturaVenta::lockForUpdate()->findOrFail($factura->id);
            if ($f->es_electronica && !empty($f->cufe)) {
                throw new RuntimeException("La factura {$f->numero} ya fue emitida electrónicamente.");
            }
            if ($f->emitiendo_at && $f->emitiendo_at->diffInSeconds(now()) < 60) {
                throw new RuntimeException("Ya hay una emisión en curso para {$f->numero} (iniciada hace " . $f->emitiendo_at->diffInSeconds(now()) . 's).');
            }
            $f->emitiendo_at = now();
            $f->save();
            return $f->load(['contacto', 'items.variante.producto']);
        });

        $payload = $this->construirPayload($factura, $config);

        Log::channel('single')->info('[SIIGO emisión] enviando', [
            'factura_id' => $factura->id,
            'numero' => $factura->numero,
        ]);

        // 2. HTTP a SIIGO — FUERA de la transacción, con try/finally para limpiar la bandera SIEMPRE
        try {
            $response = $this->cliente->request('POST', '/v1/invoices', $payload);
        } catch (\Throwable $e) {
            // Excepción de red / DNS / timeout: liberar bandera y agendar reintento
            $factura->update(['emitiendo_at' => null]);
            \App\Jobs\ReintentarEmisionDian::dispatch($factura->id)->delay(now()->addMinute());
            NotificacionErp::crear([
                'tipo' => 'timbrado_rechazado',
                'titulo' => "Error de red al emitir {$factura->numero}",
                'mensaje' => 'Se agendaron reintentos automáticos. Error: ' . $e->getMessage(),
                'color' => 'warning',
                'icono' => 'heroicon-o-arrow-path',
                'url' => '/admin/facturas-venta',
            ]);
            throw new RuntimeException("Error de red hacia SIIGO: {$e->getMessage()} — se agendó reintento.");
        }

        if ($response->failed()) {
            // Rechazo HTTP de SIIGO (4xx/5xx): agendar reintento + limpiar bandera
            $factura->update(['emitiendo_at' => null]);
            \App\Jobs\ReintentarEmisionDian::dispatch($factura->id)->delay(now()->addMinutes(5));

            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'Siigo rechazó la factura (respuesta ' . $response->status() . ').');

            NotificacionErp::crear([
                'tipo' => 'timbrado_rechazado',
                'titulo' => "SIIGO rechazó {$factura->numero} (HTTP {$response->status()})",
                'mensaje' => $msg . ' — se agendó reintento en 5min.',
                'color' => 'danger',
                'icono' => 'heroicon-o-x-circle',
                'url' => '/admin/facturas-venta',
            ]);
            throw new RuntimeException("Siigo rechazó la factura: {$msg}");
        }

        $data = (array) $response->json();

        // 3. Persistir resultado + limpiar bandera (todo transaccional)
        $factura = DB::transaction(function () use ($factura, $data) {
            $stamp = (array) ($data['stamp'] ?? []);
            $stampStatus = (string) ($stamp['status'] ?? 'Unknown');
            $cufe = (string) ($stamp['cufe'] ?? $stamp['cude'] ?? '');

            $factura->fill([
                'siigo_id' => (string) ($data['id'] ?? ''),
                'numero_siigo' => (string) ($data['name'] ?? $data['number'] ?? ''),
                'cufe' => $cufe,
                'stamp_status' => $stampStatus,
                // Guardamos respuesta cruda pero excluimos de auditoría (ver FacturaVenta::$auditExclude)
                'siigo_response' => $data,
                'es_electronica' => true,
                'emitiendo_at' => null,
            ]);

            // Token público único (retry si colisión, aunque 48-char random es ~imposible)
            if ($factura->token_publico === null) {
                do {
                    $token = Str::random(48);
                } while (FacturaVenta::where('token_publico', $token)->exists());
                $factura->token_publico = $token;
            }

            if ($cufe !== '' && $this->stampAceptado($stampStatus)) {
                $factura->emitida_at = now();
                $factura->qr_html = $this->qr->generarSvg($factura);
                $factura->qr_url = $this->qr->urlConsultaDian($cufe);
                if ($factura->estado === EstadoFactura::Borrador) {
                    $factura->estado = EstadoFactura::Pendiente;
                }
            } else {
                Log::channel('single')->warning('[SIIGO emisión] timbrado no aceptado', [
                    'factura_id' => $factura->id,
                    'stamp_status' => $stampStatus,
                    'errors' => $stamp['errors'] ?? null,
                ]);
            }

            $factura->save();
            return $factura;
        });

        // 4. Side-effects FUERA de la transacción (email + notif)
        $mailStatus = 'omitido';
        if ($factura->es_electronica && $factura->emitida_at) {
            $mailStatus = $this->enviarPorEmail($factura);

            NotificacionErp::crear([
                'tipo' => 'timbrado_ok',
                'titulo' => "Factura {$factura->numero} timbrada",
                'mensaje' => "SIIGO nº {$factura->numero_siigo} · CUFE " . substr((string) $factura->cufe, 0, 24) . '… · Email: ' . $mailStatus,
                'color' => 'success',
                'icono' => 'heroicon-o-check-badge',
                'url' => '/admin/facturas-venta',
            ]);
        } else {
            NotificacionErp::crear([
                'tipo' => 'timbrado_rechazado',
                'titulo' => "DIAN rechazó factura {$factura->numero}",
                'mensaje' => "Stamp: {$factura->stamp_status}. Se agendaron reintentos automáticos.",
                'color' => 'danger',
                'icono' => 'heroicon-o-x-circle',
                'url' => '/admin/facturas-venta',
            ]);
            // Agenda reintento con backoff exponencial
            \App\Jobs\ReintentarEmisionDian::dispatch($factura->id)->delay(now()->addMinute());
        }

        return $factura;
    }

    private function validarElegible(FacturaVenta $factura): void
    {
        if ($factura->es_electronica && !empty($factura->cufe)) {
            throw new RuntimeException("La factura {$factura->numero} ya fue emitida electrónicamente (CUFE: " . substr($factura->cufe, 0, 20) . '…).');
        }

        if ($factura->items->isEmpty()) {
            $factura->load('items');
        }

        if ($factura->items->isEmpty()) {
            throw new RuntimeException('La factura no tiene ítems.');
        }

        if (empty($factura->contacto?->numero_documento)) {
            throw new RuntimeException('El cliente no tiene identificación — obligatoria para DIAN.');
        }
    }

    private function validarConfig(): SiigoConfig
    {
        $config = SiigoConfig::current();

        if (! $config->activo) {
            throw new RuntimeException('La integración con Siigo está desactivada. Actívala en Configuración → Integración Siigo.');
        }

        $labels = [
            'tipo_documento_id' => 'Tipo de documento (Document Type ID)',
            'seller_id' => 'Vendedor Siigo (Seller ID)',
            'payment_type_id' => 'Método de pago Siigo (Payment Type ID)',
        ];

        foreach ($labels as $col => $label) {
            if (empty($config->{$col})) {
                throw new RuntimeException("Falta configurar «{$label}» en Configuración → Integración Siigo.");
            }
        }

        return $config;
    }

    private function construirPayload(FacturaVenta $factura, SiigoConfig $config): array
    {
        $items = $factura->items->map(function ($item) {
            $variante = $item->variante;
            $codigo = $variante?->codigo_barras ?? "ITEM-{$item->id}";
            return [
                'code' => (string) $codigo,
                'description' => (string) ($item->descripcion ?? $variante?->producto?->nombre ?? 'Ítem'),
                'quantity' => (float) $item->cantidad,
                'price' => (float) $item->precio_unit,
                'discount' => (float) ($item->descuento_pct ?? 0),
            ];
        })->all();

        $payload = [
            'document' => ['id' => (int) $config->tipo_documento_id],
            'date' => $factura->fecha_emision->format('Y-m-d'),
            'customer' => ['identification' => (string) $factura->contacto->numero_documento],
            'seller' => (int) $config->seller_id,
            'items' => $items,
            'payments' => [[
                'id' => (int) $config->payment_type_id,
                'value' => (float) $factura->total,
                'due_date' => $factura->fecha_vencimiento?->format('Y-m-d')
                    ?? $factura->fecha_emision->format('Y-m-d'),
            ]],
            'stamp' => ['send' => true],
            'mail' => ['send' => false],
        ];

        if (! empty($factura->observaciones)) {
            $payload['observations'] = (string) $factura->observaciones;
        }

        return $payload;
    }

    /**
     * Encola el envío del email al cliente (queue en producción, sync en local).
     * Retorna 'ok' | 'omitido' | 'falló'.
     */
    private function enviarPorEmail(FacturaVenta $factura): string
    {
        $email = $factura->contacto?->email;
        if (empty($email)) {
            return 'omitido (contacto sin email)';
        }

        try {
            $link = route('cartera.factura.publica', $factura->token_publico);
            Mail::to($email)->queue(new FacturaClienteMail($factura, $link));

            Log::channel('single')->info('[SIIGO emisión] email encolado', [
                'factura_id' => $factura->id, 'to' => $email,
            ]);
            return 'encolado';
        } catch (\Throwable $e) {
            Log::channel('single')->warning('[SIIGO emisión] fallo al encolar email', [
                'factura_id' => $factura->id, 'error' => $e->getMessage(),
            ]);
            return 'falló: ' . $e->getMessage();
        }
    }

    private function stampAceptado(string $status): bool
    {
        return in_array(strtolower($status), ['accepted', 'aceptado', 'enviado', 'ok', 'success'], true);
    }
}
