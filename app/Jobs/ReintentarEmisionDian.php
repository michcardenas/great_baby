<?php

namespace App\Jobs;

use App\Models\NotificacionErp;
use App\Modules\Cartera\Models\FacturaVenta;
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

        $svc->emitir($factura);

        NotificacionErp::crear([
            'tipo' => 'timbrado_ok',
            'titulo' => "Factura {$factura->numero} timbrada en reintento",
            'mensaje' => "SIIGO nº {$factura->fresh()->numero_siigo} · CUFE " . substr((string) $factura->fresh()->cufe, 0, 20) . '…',
            'color' => 'success',
            'icono' => 'heroicon-o-check-badge',
            'url' => '/admin/facturas-venta',
        ]);
    }

    public function failed(\Throwable $e): void
    {
        NotificacionErp::crear([
            'tipo' => 'timbrado_rechazado',
            'titulo' => "Emisión DIAN falló tras 4 intentos",
            'mensaje' => "Factura #{$this->facturaId}: " . $e->getMessage(),
            'color' => 'danger',
            'icono' => 'heroicon-o-x-circle',
            'url' => '/admin/facturas-venta',
        ]);
    }
}
