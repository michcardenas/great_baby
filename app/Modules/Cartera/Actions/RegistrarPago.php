<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Enums\ClasificacionDiferencia;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * TO-BE Contabilidad P2 — Registrar pago aplicado a factura con clasificación automática de diferencia.
 *   - Cliente paga total esperado → sin diferencia
 *   - Paga menos y dentro del plazo pronto pago (según condición de crédito) → descuento_pronto_pago
 *   - Paga menos y GB asume flete (condición) → flete_asumido_gb
 *   - Paga menos y no aplica descuento → saldo_pendiente
 *   - Paga más → sobre_pago
 */
class RegistrarPago
{
    use AsAction;

    /**
     * @return PagoVenta
     */
    public function handle(
        int $facturaId,
        float $montoRecibido,
        string $fecha,
        string $medioPago = 'transferencia',
        ?string $referencia = null,
        ?string $banco = null,
        ?int $userId = null,
        ?ClasificacionDiferencia $override = null,
        ?string $notas = null,
    ): PagoVenta {
        return DB::transaction(function () use ($facturaId, $montoRecibido, $fecha, $medioPago, $referencia, $banco, $userId, $override, $notas) {
            $factura = FacturaVenta::with('contacto.condicionVigente')->lockForUpdate()->findOrFail($facturaId);

            $saldoAntes = (float) $factura->saldo;
            $diferencia = round($saldoAntes - $montoRecibido, 2);
            $clasificacion = $override ?? $this->clasificar($factura, $diferencia, $fecha);

            // Aplicado = lo que cierra el saldo (para clasificaciones que consumen la diferencia)
            $consumeDiferencia = in_array($clasificacion, [
                ClasificacionDiferencia::DescuentoProntoPago,
                ClasificacionDiferencia::FleteAsumidoGb,
                ClasificacionDiferencia::DescuentoFueraPlazo,
            ], true);

            $montoAplicado = $consumeDiferencia ? $saldoAntes : min($montoRecibido, $saldoAntes);

            $pago = PagoVenta::create([
                'factura_id' => $factura->id,
                'contacto_id' => $factura->contacto_id,
                'fecha' => $fecha,
                'monto_recibido' => $montoRecibido,
                'monto_aplicado' => $montoAplicado,
                'diferencia' => $diferencia,
                'clasificacion_diferencia' => $clasificacion,
                'medio_pago' => $medioPago,
                'referencia' => $referencia,
                'banco' => $banco,
                'registrado_por' => $userId,
                'notas' => $notas,
            ]);

            $factura->recalcular();

            return $pago;
        });
    }

    protected function clasificar(FacturaVenta $factura, float $diferencia, string $fecha): ClasificacionDiferencia
    {
        if (abs($diferencia) < 0.01) {
            return ClasificacionDiferencia::SaldoPendiente; // pago exacto → nada especial
        }
        if ($diferencia < 0) {
            return ClasificacionDiferencia::SobrePago;
        }

        $cond = $factura->contacto->condicionVigente;
        if ($cond) {
            $diasHastaPago = $factura->fecha_emision->diffInDays($fecha, false);
            $descuentoEsperado = round((float) $factura->total * ((float) $cond->descuento_pronto_pago_pct / 100), 2);

            if ($cond->descuento_pronto_pago_pct > 0
                && $diasHastaPago <= $cond->plazo_pronto_pago_dias
                && abs($diferencia - $descuentoEsperado) < 100) {
                return ClasificacionDiferencia::DescuentoProntoPago;
            }
            if ($cond->flete_asumido_gb && $diferencia > 0 && $diferencia < 50000) {
                return ClasificacionDiferencia::FleteAsumidoGb;
            }
            if ($cond->descuento_pronto_pago_pct > 0 && $diasHastaPago > $cond->plazo_pronto_pago_dias) {
                return ClasificacionDiferencia::DescuentoFueraPlazo;
            }
        }

        return ClasificacionDiferencia::SaldoPendiente;
    }
}
