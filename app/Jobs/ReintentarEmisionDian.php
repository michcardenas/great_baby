<?php

namespace App\Jobs;

use App\Models\NotificacionErp;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Siigo\Models\SiigoSyncLog;
use App\Modules\Siigo\Services\SiigoEmisionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Reintento automático con backoff exponencial (1min, 5min, 30min, 3h).
 * Se dispara cuando el timbrado inicial fue rechazado o hubo error de red.
 */
class ReintentarEmisionDian implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public array $backoff = [60, 300, 1800, 10800]; // 1min, 5min, 30min, 3h

    public function __construct(public int $facturaId) {}

    public function handle(SiigoEmisionService $svc): void
    {
        $factura = FacturaVenta::with(['contacto', 'items.variante.producto'])->find($this->facturaId);
        if (! $factura) {
            Log::warning("[ReintentarEmisionDian] Factura {$this->facturaId} no existe.");
            return;
        }

        if ($factura->es_electronica && $factura->cufe && strtolower((string) $factura->stamp_status) === 'accepted') {
            Log::info("[ReintentarEmisionDian] Factura {$factura->numero} ya está aceptada, no re-emite.");
            return;
        }

        // Ya está en SIIGO pero la DIAN no la timbró.
        //
        // Este job re-emite, y re-emitir un documento que ya existe allá lo
        // duplicaría en cuanto caduque la clave de idempotencia, gastando
        // numeración de la DIAN. Se vio en vivo: 11 reintentos encolados para
        // la misma factura que ya estaba en SIIGO. Lo que falta ahí es volver
        // a timbrar, que es otra operación y se hace desde SIIGO.
        if ($factura->siigo_id) {
            Log::warning('[ReintentarEmisionDian] Factura ya existe en SIIGO · no se re-emite', [
                'factura' => $factura->numero,
                'siigo' => $factura->numero_siigo,
                'stamp_status' => $factura->stamp_status,
            ]);

            SiigoSyncLog::create([
                'recurso' => 'facturas_venta',
                'estado' => 'omitido',
                'mensaje' => "Factura {$factura->numero} ya está en SIIGO como {$factura->numero_siigo}; "
                    ."el timbrado quedó en «{$factura->stamp_status}». Reintentar el timbrado desde SIIGO.",
                'detalle' => ['factura_id' => $factura->id, 'siigo_id' => $factura->siigo_id],
            ]);

            return;
        }

        $svc->emitir($factura);
        $fresh = $factura->fresh();

        // A2 FIX · panel CONT-C2 lee de siigo_sync_log. Antes este Job solo
        //   escribía NotificacionErp → facturas B2B invisibles al reporte de
        //   discrepancias. Ahora quedan trazadas igual que NC/asientos/pagos.
        SiigoSyncLog::create([
            'recurso' => 'facturas_venta',
            'estado' => 'ok',
            'mensaje' => "Factura {$factura->numero} timbrada · SIIGO " . ($fresh?->numero_siigo ?? '—'),
            'detalle' => [
                'factura_id' => $factura->id,
                'numero' => $factura->numero,
                'cufe' => substr((string) $fresh?->cufe, 0, 60),
            ],
        ]);

        NotificacionErp::crear([
            'tipo' => 'timbrado_ok',
            'titulo' => "Factura {$factura->numero} timbrada en reintento",
            'mensaje' => "SIIGO nº {$fresh?->numero_siigo} · CUFE " . substr((string) $fresh?->cufe, 0, 20) . '…',
            'color' => 'success',
            'icono' => 'heroicon-o-check-badge',
            'url' => '/app/facturas',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        // A2 FIX · dejar rastro en siigo_sync_log para CONT-C2.
        SiigoSyncLog::create([
            'recurso' => 'facturas_venta',
            'estado' => 'fallido',
            'mensaje' => mb_substr($e->getMessage(), 0, 500),
            'detalle' => ['factura_id' => $this->facturaId],
        ]);

        NotificacionErp::crear([
            'tipo' => 'timbrado_rechazado',
            'titulo' => "Emisión DIAN falló tras 4 intentos",
            'mensaje' => "Factura #{$this->facturaId}: " . $e->getMessage(),
            'color' => 'danger',
            'icono' => 'heroicon-o-x-circle',
            'url' => '/app/facturas',
        ]);
    }
}
