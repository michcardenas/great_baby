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
        // Fix H-2 CRÍTICO re-audit · trazabilidad SIIGO/DIAN para items agregados.
        //   Antes: para item con variante_id NULL (agregado) enviábamos
        //   `ITEM-{id}` como code → SIIGO creaba productos ghost o rechazaba.
        //   Ahora: fallback a la referencia del producto agregado. La migración
        //   100011 agregó producto_id justo para esto.
        $factura->loadMissing(['items.variante.producto', 'items.producto']);

        $items = $factura->items->map(function ($item) {
            $variante = $item->variante;
            $producto = $variante?->producto ?? $item->producto;
            $codigo = $variante?->codigo_barras
                ?? $producto?->referencia
                ?? "ITEM-{$item->id}";
            return [
                'code' => (string) $codigo,
                'description' => (string) ($item->descripcion ?? $producto?->nombre ?? 'Ítem'),
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

    /**
     * F9 · Emite una FACTURA DE COMPRA (proveedor) contra SIIGO.
     * Endpoint: POST /v1/purchases
     * Se dispara al confirmar una `RecepcionCompra` (mercancía recibida).
     *
     * Reutiliza el mismo cliente HTTP + logging + rate limit del sync de
     * productos. Idempotente: si la recepción ya tiene `siigo_id`, no re-emite.
     *
     * @param  \App\Modules\Compras\Models\RecepcionCompra  $r
     * @return \App\Modules\Compras\Models\RecepcionCompra
     */
    public function emitirCompra(\App\Modules\Compras\Models\RecepcionCompra $r): \App\Modules\Compras\Models\RecepcionCompra
    {
        if ($r->siigo_id) {
            return $r; // ya emitida · idempotente
        }
        if ($r->estado !== 'confirmada') {
            throw new RuntimeException("La recepción {$r->numero} no está confirmada.");
        }

        $r->loadMissing(['orden.proveedor', 'items.ordenItem.producto', 'bodega']);

        if (empty($r->orden?->proveedor?->numero_documento)) {
            throw new RuntimeException("El proveedor de la OC {$r->orden->numero} no tiene NIT.");
        }

        // Items: reutiliza referencias de producto/variante del ERP.
        $items = $r->items->map(function ($i) {
            $ordenItem = $i->ordenItem;
            $producto = $ordenItem?->producto;
            $variante = $ordenItem?->variante;
            $code = $variante?->codigo_barras ?? $producto?->referencia ?? "RCITEM-{$i->id}";
            return [
                'code' => (string) $code,
                'description' => (string) ($producto?->nombre ?? 'Ítem'),
                'quantity' => (float) $i->cantidad_recibida,
                'price' => (float) ($ordenItem?->costo_unit ?? 0),
                'discount' => 0,
            ];
        })->filter(fn ($it) => $it['quantity'] > 0)->values()->all();

        if (empty($items)) {
            throw new RuntimeException("La recepción {$r->numero} no tiene ítems recibidos.");
        }

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_compra', 0)],
            'date' => $r->fecha_recepcion->format('Y-m-d'),
            'supplier' => ['identification' => (string) $r->orden->proveedor->numero_documento],
            'cost_center' => (int) setting('siigo.cost_center_compras', 0) ?: null,
            'currency' => ['code' => 'COP'],
            'items' => $items,
            'observations' => "Recepción {$r->numero} · OC {$r->orden->numero}",
        ];
        $payload = array_filter($payload, fn ($v) => $v !== null && $v !== 0);

        Log::channel('single')->info('[SIIGO compra] enviando', [
            'recepcion_id' => $r->id, 'numero' => $r->numero,
        ]);

        $response = $this->cliente->request('POST', '/v1/purchases', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó la factura de compra (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó compra {$r->numero}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para compra {$r->numero}.");
        }

        $r->forceFill([
            'siigo_id' => (string) $data['id'],
            'siigo_number' => (string) ($data['name'] ?? $data['number'] ?? ''),
            'siigo_sync_at' => now(),
        ])->save();

        return $r;
    }

    /**
     * F10 · Emite un VOUCHER (recibo de caja / comprobante de egreso) contra
     * SIIGO cuando se registra un pago cliente en Cartera. Endpoint: POST /v1/vouchers.
     *
     * Idempotente: si el pago ya tiene `siigo_id`, retorna sin re-enviar.
     * Aplica el pago contra la factura de venta correspondiente (que ya debe
     * tener su `siigo_id` — se emite por SiigoEmisionService::emitir() previamente).
     *
     * @param  \App\Modules\Cartera\Models\PagoVenta  $p
     * @return \App\Modules\Cartera\Models\PagoVenta
     */
    public function emitirVoucher(\App\Modules\Cartera\Models\PagoVenta $p): \App\Modules\Cartera\Models\PagoVenta
    {
        if ($p->siigo_id) {
            return $p; // ya emitido · idempotente
        }

        $p->loadMissing(['factura', 'contacto']);

        if (! $p->factura?->siigo_id) {
            throw new RuntimeException(
                "El pago {$p->id} apunta a factura {$p->factura?->numero} que aún no está en SIIGO · " .
                "espera a que la factura se emita antes de aplicar el pago."
            );
        }
        if (empty($p->contacto?->numero_documento)) {
            throw new RuntimeException("El contacto del pago {$p->id} no tiene NIT.");
        }

        $monto = (float) $p->monto_aplicado;
        if ($monto <= 0) {
            throw new RuntimeException("El pago {$p->id} tiene monto_aplicado 0.");
        }

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_recibo_caja', 0)],
            'date' => $p->fecha->format('Y-m-d'),
            'customer' => ['identification' => (string) $p->contacto->numero_documento],
            'currency' => ['code' => 'COP'],
            'items' => [[
                'due' => ['prefix' => 'FV', 'consecutive' => (int) $p->factura->consecutivo ?: 0,
                          'quote' => 1, 'date' => $p->factura->fecha_emision->format('Y-m-d')],
                'invoice_id' => (string) $p->factura->siigo_id,
                'value' => $monto,
            ]],
            'payment' => [
                'id' => (int) setting('siigo.payment_type_recibo', 0)
                    ?: (int) SiigoConfig::current()->payment_type_id,
                'value' => $monto,
            ],
            'observations' => "ERP pago #{$p->id} · " . ($p->medio_pago ?? 'sin medio')
                . ($p->referencia ? " · ref {$p->referencia}" : ''),
        ];
        // Filtrar defaults 0/null.
        if (empty($payload['document']['id'])) unset($payload['document']);
        if (empty($payload['payment']['id'])) {
            throw new RuntimeException(
                "Falta configurar 'siigo.payment_type_recibo' o payment_type_id en SiigoConfig."
            );
        }

        Log::channel('single')->info('[SIIGO voucher] enviando', [
            'pago_id' => $p->id, 'monto' => $monto,
        ]);

        $response = $this->cliente->request('POST', '/v1/vouchers', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó el voucher (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó voucher pago={$p->id}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para voucher pago={$p->id}.");
        }

        $p->forceFill([
            'siigo_id' => (string) $data['id'],
            'siigo_number' => (string) ($data['name'] ?? $data['number'] ?? ''),
            'siigo_sync_at' => now(),
        ])->save();

        return $p;
    }

    /**
     * Sprint 4 · B.1 · Emite una NOTA CRÉDITO manual a SIIGO.
     * Endpoint: POST /v1/credit-notes
     *
     * Diferencia con la NC de Dropi: esta se dispara desde Cartera cuando
     * Aracely genera manualmente una NC (devolución parcial, error de factura,
     * descuento post-venta). La de Dropi vive en EmitirNotaCreditoDropi.
     *
     * Idempotente · si ya tiene siigo_id, retorna.
     */
    public function emitirNotaCredito(\App\Modules\Cartera\Models\NotaCredito $nc): \App\Modules\Cartera\Models\NotaCredito
    {
        if ($nc->siigo_id) return $nc;

        $nc->loadMissing(['factura', 'factura.contacto']);
        if (! $nc->factura?->siigo_id) {
            throw new RuntimeException("La NC {$nc->numeroCompleto()} apunta a factura que aún no está en SIIGO.");
        }
        if (empty($nc->factura->contacto?->numero_documento)) {
            throw new RuntimeException("El cliente de la factura no tiene NIT.");
        }

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_nota_credito', 0)],
            'number' => (int) $nc->numero,
            'name' => $nc->numeroCompleto(),
            'date' => now()->format('Y-m-d'),
            'invoice' => (string) $nc->factura->siigo_id,
            'cause' => 2, // 2=devolución mercancía, 3=descuento, 4=anulación
            'customer' => ['identification' => (string) $nc->factura->contacto->numero_documento],
            'items' => [[
                'code' => 'NC-' . $nc->numero,
                'description' => $nc->motivo ?: 'Nota crédito manual',
                'quantity' => 1,
                'price' => (float) $nc->valor,
                'discount' => 0,
            ]],
            'observations' => "NC manual ERP #{$nc->id} · " . ($nc->motivo ?: 'sin motivo'),
        ];
        if (empty($payload['document']['id'])) unset($payload['document']);

        Log::channel('single')->info('[SIIGO NC] enviando', [
            'nc_id' => $nc->id, 'numero' => $nc->numeroCompleto(),
        ]);

        $response = $this->cliente->request('POST', '/v1/credit-notes', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó la NC (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó NC {$nc->numeroCompleto()}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para NC {$nc->numeroCompleto()}.");
        }

        $nc->forceFill([
            'siigo_id' => (string) $data['id'],
            'siigo_response' => $data,
            'cufe' => (string) ($data['stamp']['cufe'] ?? $data['stamp']['cude'] ?? ''),
            'emitida_at' => now(),
        ])->save();

        return $nc;
    }

    /**
     * QA-FIX #7 · Sprint 4 · B.3+ · Voucher EGRESO por pago a proveedor.
     * Endpoint: POST /v1/vouchers · type=egreso.
     * Persiste el neto pagado (después de retenciones) y anota las retenciones
     * como items separados por si SIIGO las quiere ver detalladas.
     */
    public function emitirVoucherEgreso(\App\Modules\Cartera\Models\PagoProveedor $p): \App\Modules\Cartera\Models\PagoProveedor
    {
        if ($p->siigo_voucher_id) return $p;

        $p->loadMissing(['proveedor', 'retenciones']);
        if (empty($p->proveedor?->numero_documento)) {
            throw new RuntimeException("Pago #{$p->id}: proveedor sin NIT.");
        }

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_egreso', 0)],
            'date' => $p->fecha->format('Y-m-d'),
            'customer' => ['identification' => (string) $p->proveedor->numero_documento],
            'currency' => ['code' => 'COP'],
            'items' => [[
                'account' => ['code' => (string) $p->cuenta_puc_egreso, 'movement' => 'Credit'],
                'value' => (float) $p->monto_neto,
                'description' => "Pago proveedor {$p->proveedor->nombre_completo}",
            ]],
            'payment' => [
                'id' => (int) setting('siigo.payment_type_egreso', setting('siigo.payment_type_recibo', 0)),
                'value' => (float) $p->monto_neto,
            ],
            'observations' => "ERP pago proveedor #{$p->id} · {$p->metodo}"
                . ($p->monto_retenciones > 0 ? " · retenciones \$" . number_format($p->monto_retenciones, 2) : ''),
        ];
        if (empty($payload['document']['id'])) unset($payload['document']);
        if (empty($payload['payment']['id'])) {
            throw new RuntimeException("Falta configurar 'siigo.payment_type_egreso' en settings.");
        }

        Log::channel('single')->info('[SIIGO egreso] enviando', [
            'pago_proveedor_id' => $p->id, 'neto' => $p->monto_neto,
        ]);

        $response = $this->cliente->request('POST', '/v1/vouchers', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó voucher egreso (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó egreso pago #{$p->id}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para egreso pago #{$p->id}.");
        }

        $p->forceFill([
            'siigo_voucher_id' => (string) $data['id'],
            'siigo_response' => $data,
            'siigo_sync_at' => now(),
        ])->save();

        return $p;
    }

    /**
     * Sprint 4 · B.2 · Emite una NOTA DÉBITO manual a SIIGO.
     * Endpoint: POST /v1/debit-notes
     * Causal típica: recargo por mora, ajuste al alza post-factura.
     */
    public function emitirNotaDebito(\App\Modules\Cartera\Models\NotaDebito $nd): \App\Modules\Cartera\Models\NotaDebito
    {
        if ($nd->siigo_id) return $nd;

        $nd->loadMissing(['factura', 'factura.contacto']);
        if (! $nd->factura?->siigo_id) {
            throw new RuntimeException("La ND {$nd->numeroCompleto()} apunta a factura que aún no está en SIIGO.");
        }
        if (empty($nd->factura->contacto?->numero_documento)) {
            throw new RuntimeException("El cliente de la factura no tiene NIT.");
        }

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_nota_debito', 0)],
            'number' => (int) $nd->numero,
            'name' => $nd->numeroCompleto(),
            'date' => now()->format('Y-m-d'),
            'invoice' => (string) $nd->factura->siigo_id,
            'cause' => 1, // 1=intereses/recargo (SIIGO)
            'customer' => ['identification' => (string) $nd->factura->contacto->numero_documento],
            'items' => [[
                'code' => 'ND-' . $nd->numero,
                'description' => $nd->motivo ?: 'Nota débito manual',
                'quantity' => 1,
                'price' => (float) $nd->valor,
                'discount' => 0,
            ]],
            'observations' => "ND manual ERP #{$nd->id} · " . ($nd->motivo ?: 'sin motivo'),
        ];
        if (empty($payload['document']['id'])) unset($payload['document']);

        Log::channel('single')->info('[SIIGO ND] enviando', [
            'nd_id' => $nd->id, 'numero' => $nd->numeroCompleto(),
        ]);

        $response = $this->cliente->request('POST', '/v1/debit-notes', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó la ND (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó ND {$nd->numeroCompleto()}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para ND {$nd->numeroCompleto()}.");
        }

        $nd->forceFill([
            'siigo_id' => (string) $data['id'],
            'siigo_response' => $data,
            'cufe' => (string) ($data['stamp']['cufe'] ?? $data['stamp']['cude'] ?? ''),
            'emitida_at' => now(),
        ])->save();

        return $nd;
    }

    /**
     * Sprint 4 · B.4 · Emite un ASIENTO MANUAL a SIIGO (multi-línea).
     * Diferente al emitirAsiento (que agrupa 2 líneas por movimiento kardex):
     * este recibe un AsientoManual con N líneas que Aracely armó desde Vue.
     */
    public function emitirAsientoManual(\App\Modules\Contabilidad\Models\AsientoManual $a): \App\Modules\Contabilidad\Models\AsientoManual
    {
        if ($a->siigo_journal_id) return $a;

        $a->loadMissing('lineas');
        if (! $a->cuadra()) {
            throw new RuntimeException("Asiento #{$a->id} no cuadra · débito ≠ crédito.");
        }

        $items = $a->lineas->map(function ($l) {
            $mov = $l->debe > 0 ? 'Debit' : 'Credit';
            $valor = (float) ($l->debe > 0 ? $l->debe : $l->haber);
            $row = [
                'account' => ['code' => (string) $l->cuenta_puc, 'movement' => $mov],
                'value' => $valor,
                'description' => (string) $l->descripcion,
            ];
            if ($l->tercero_documento) {
                $row['customer'] = ['identification' => (string) $l->tercero_documento];
            }
            return $row;
        })->all();

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_asiento_manual', setting('siigo.doc_type_gasto', 0))],
            'date' => $a->fecha->format('Y-m-d'),
            'items' => $items,
            'observations' => "ERP asiento manual #{$a->id} · {$a->glosa}",
        ];
        if (empty($payload['document']['id'])) unset($payload['document']);

        Log::channel('single')->info('[SIIGO asiento manual] enviando', [
            'asiento_id' => $a->id, 'lineas' => count($items), 'valor' => $a->valor_total,
        ]);

        $response = $this->cliente->request('POST', '/v1/journals', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó asiento manual (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó asiento manual #{$a->id}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para asiento manual #{$a->id}.");
        }

        $a->forceFill([
            'siigo_journal_id' => (string) $data['id'],
            'siigo_sync_at' => now(),
            'estado' => 'sincronizado',
        ])->save();

        return $a;
    }

    /**
     * F11 · Emite un ASIENTO CONTABLE en SIIGO por un movimiento de inventario.
     * Endpoint: POST /v1/journals.
     *
     * La matriz de cuentas (débito/crédito por tipo de movimiento) queda
     * documentada en las notas del asiento; los valores concretos se toman
     * de los defaults de Reglas de Negocio (cta_inventario, cta_costo,
     * cta_ajuste_stock) que Silvia confirma con el PUC Great Baby.
     *
     * Idempotente: si el movimiento ya tiene siigo_journal_id, retorna.
     *
     * @param  \App\Modules\Dropi\Models\InventarioMovimiento  $m
     */
    public function emitirAsiento(\App\Modules\Dropi\Models\InventarioMovimiento $m): \App\Modules\Dropi\Models\InventarioMovimiento
    {
        if ($m->siigo_journal_id) return $m;

        $m->loadMissing(['ubicacion']);
        $valor = round(abs((float) $m->cantidad) * (float) ($m->costo_unit ?? 0), 2);
        if ($valor <= 0) {
            throw new RuntimeException("Movimiento kardex {$m->id} sin costo_unit · no se puede asentar.");
        }

        // Cuentas por tipo (Great Baby PUC · confirmar con Silvia los subcódigos):
        //   traslado_salida/entrada  → mismo neteo entre subcuentas de inventario
        //   merma / faltante          → Débito 5195 Gasto / Crédito 1435 Inventario
        //   sobrante                  → Débito 1435 Inventario / Crédito 4295 Otros ingresos
        [$cuentaDebito, $cuentaCredito, $concepto] = match ($m->tipo) {
            'traslado_salida'      => [setting('contable.cta_inventario_default', '1435'), setting('contable.cta_inventario_default', '1435'), 'Traslado salida'],
            'traslado_entrada'     => [setting('contable.cta_inventario_default', '1435'), setting('contable.cta_inventario_default', '1435'), 'Traslado entrada'],
            'merma', 'faltante'    => [setting('siigo.cta_gasto_default', '5195'), setting('contable.cta_inventario_default', '1435'), 'Merma inventario'],
            'sobrante'             => [setting('contable.cta_inventario_default', '1435'), '4295', 'Sobrante inventario'],
            'ajuste_toma_fisica'   => [setting('siigo.cta_gasto_default', '5195'), setting('contable.cta_inventario_default', '1435'), 'Ajuste toma física'],
            default                => throw new RuntimeException("Tipo de movimiento '{$m->tipo}' no configurado para asiento SIIGO."),
        };

        $payload = [
            'document' => ['id' => (int) setting('siigo.doc_type_gasto', 0)],
            'date' => $m->created_at->format('Y-m-d'),
            'items' => [
                ['account' => ['code' => (string) $cuentaDebito, 'movement' => 'Debit'], 'value' => $valor,
                 'description' => "{$concepto} · mov {$m->id}"],
                ['account' => ['code' => (string) $cuentaCredito, 'movement' => 'Credit'], 'value' => $valor,
                 'description' => "{$concepto} · mov {$m->id}"],
            ],
            'observations' => "ERP mov #{$m->id} · {$m->tipo} · ubicación " . optional($m->ubicacion)->nombre,
        ];
        if (empty($payload['document']['id'])) unset($payload['document']);

        Log::channel('single')->info('[SIIGO asiento] enviando', [
            'mov_id' => $m->id, 'tipo' => $m->tipo, 'valor' => $valor,
        ]);

        $response = $this->cliente->request('POST', '/v1/journals', $payload);

        if ($response->failed()) {
            $msg = (string) ($response->json('Errors.0.Message')
                ?? $response->json('message')
                ?? 'SIIGO rechazó el asiento (HTTP ' . $response->status() . ').');
            throw new RuntimeException("SIIGO rechazó asiento mov={$m->id}: {$msg}");
        }

        $data = (array) $response->json();
        if (empty($data['id'])) {
            throw new RuntimeException("SIIGO respondió 200 sin ID para asiento mov={$m->id}.");
        }

        $m->forceFill([
            'siigo_journal_id' => (string) $data['id'],
            'siigo_sync_at' => now(),
        ])->save();

        return $m;
    }
}
