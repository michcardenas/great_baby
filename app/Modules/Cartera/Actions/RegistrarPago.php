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

            // Re-audit RAÍZ A/C (FUNC N1 / DATOS #1) · guard duro. Sin este
            // check un pago sobre Borrador/Anulada creaba débito caja + crédito
            // 1305 sin asiento de emisión previo → CxC negativa permanente.
            if (! $factura->puedeRecibirPago()) {
                $estadoActual = $factura->estado instanceof \App\Modules\Cartera\Enums\EstadoFactura
                    ? $factura->estado->value
                    : (string) $factura->estado;
                throw new \RuntimeException(
                    "Factura {$factura->numero} en estado '{$estadoActual}' no puede recibir pagos. Emítela o revierte la anulación primero."
                );
            }

            $saldoAntes = (float) $factura->saldo;
            $diferencia = round($saldoAntes - $montoRecibido, 2);
            $clasificacion = $override ?? $this->clasificar($factura, $diferencia, $fecha);

            // Re-audit FUNC C2 / DATOS A10 · DescuentoFueraPlazo YA NO cierra
            // la factura automáticamente. Antes cualquier pago corto tras el
            // plazo pronto-pago se "regalaba" al cliente como descuento. Ahora
            // requiere override explícito de Aracely.
            $consumeDiferencia = in_array($clasificacion, [
                ClasificacionDiferencia::DescuentoProntoPago,
                ClasificacionDiferencia::FleteAsumidoGb,
                // DescuentoFueraPlazo: solo cierra si viene por $override manual.
                ...($override === ClasificacionDiferencia::DescuentoFueraPlazo
                    ? [ClasificacionDiferencia::DescuentoFueraPlazo] : []),
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

            // Re-audit DATOS A8 · guard días negativos (fecha_pago < fecha_emision).
            // Antes clasificaba como pronto-pago un pago con fecha errada.
            if ($diasHastaPago < 0) {
                return ClasificacionDiferencia::SaldoPendiente;
            }

            $descuentoEsperado = round((float) $factura->total * ((float) $cond->descuento_pronto_pago_pct / 100), 2);
            // Re-audit DATOS A8 · tolerancia relativa (1% del descuento esperado o COP 500),
            // no fija en 100 pesos. Evita clasificar mal pagos con dif de $99.
            $tolerancia = max(500.0, $descuentoEsperado * 0.01);

            if ($cond->descuento_pronto_pago_pct > 0
                && $diasHastaPago <= $cond->plazo_pronto_pago_dias
                && abs($diferencia - $descuentoEsperado) < $tolerancia) {
                return ClasificacionDiferencia::DescuentoProntoPago;
            }
            if ($cond->flete_asumido_gb && $diferencia > 0 && $diferencia < 50000) {
                return ClasificacionDiferencia::FleteAsumidoGb;
            }
            // Re-audit FUNC C2 · antes de plazo pero cortos NO se clasifican
            // como DescuentoFueraPlazo por default — quedan como SaldoPendiente
            // hasta que Aracely lo apruebe explícitamente vía override.
            if ($cond->descuento_pronto_pago_pct > 0 && $diasHastaPago > $cond->plazo_pronto_pago_dias) {
                return ClasificacionDiferencia::SaldoPendiente;
            }
        }

        return ClasificacionDiferencia::SaldoPendiente;
    }
}
