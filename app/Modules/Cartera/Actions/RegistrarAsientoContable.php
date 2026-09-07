<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Cartera\Models\PagoVenta;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Registra los asientos de partida doble en movimientos_contables.
 * Cuentas usadas (PUC colombiano estándar):
 *   1355 - Impuestos anticipos y saldos a favor
 *   1305 - Clientes B2B (CxC)
 *   4135 - Comercio al por mayor y menor (ingreso)
 *   2408 - IVA por pagar
 *   1105 - Caja / 1110 - Bancos
 *   530510 - Descuentos comerciales condicionados
 *   5195 - Transportes fletes y acarreos
 *   2805 - Anticipos y avances recibidos (para sobrepagos)
 */
class RegistrarAsientoContable
{
    use AsAction;

    public function factura(FacturaVenta $factura): int
    {
        return DB::transaction(function () use ($factura) {
            $this->limpiarPrevios($factura);
            $terceroTipo = 'App\\Models\\Contacto';

            // CxC al cliente
            MovimientoContable::create([
                'fecha' => $factura->fecha_emision,
                'cuenta_puc' => '1305', 'tercero_type' => $terceroTipo, 'tercero_id' => $factura->contacto_id,
                'debe' => $factura->total, 'haber' => 0,
                'origen_type' => FacturaVenta::class, 'origen_id' => $factura->id,
                'descripcion' => "Factura {$factura->numero} — {$factura->contacto?->nombreDisplay()}",
                'user_id' => auth()->id(),
            ]);

            // Ingreso por venta
            $ingreso = (float) $factura->subtotal - (float) $factura->descuento;
            MovimientoContable::create([
                'fecha' => $factura->fecha_emision,
                'cuenta_puc' => '4135', 'tercero_type' => $terceroTipo, 'tercero_id' => $factura->contacto_id,
                'debe' => 0, 'haber' => $ingreso,
                'origen_type' => FacturaVenta::class, 'origen_id' => $factura->id,
                'descripcion' => "Venta {$factura->numero}",
                'user_id' => auth()->id(),
            ]);

            // IVA por pagar (si aplica)
            if ((float) $factura->impuestos > 0) {
                MovimientoContable::create([
                    'fecha' => $factura->fecha_emision,
                    'cuenta_puc' => '2408', 'tercero_type' => $terceroTipo, 'tercero_id' => $factura->contacto_id,
                    'debe' => 0, 'haber' => $factura->impuestos,
                    'origen_type' => FacturaVenta::class, 'origen_id' => $factura->id,
                    'descripcion' => "IVA factura {$factura->numero}",
                    'user_id' => auth()->id(),
                ]);
            }

            return 2 + ((float) $factura->impuestos > 0 ? 1 : 0);
        });
    }

    public function pago(PagoVenta $pago): int
    {
        return DB::transaction(function () use ($pago) {
            $this->limpiarPrevios($pago);
            $terceroTipo = 'App\\Models\\Contacto';
            $cuentaCaja = $pago->medio_pago === 'efectivo' ? '1105' : '1110';

            // Ingreso a caja/banco
            MovimientoContable::create([
                'fecha' => $pago->fecha,
                'cuenta_puc' => $cuentaCaja, 'tercero_type' => $terceroTipo, 'tercero_id' => $pago->contacto_id,
                'debe' => $pago->monto_recibido, 'haber' => 0,
                'origen_type' => PagoVenta::class, 'origen_id' => $pago->id,
                'descripcion' => "Pago {$pago->medio_pago} — factura {$pago->factura?->numero}",
                'user_id' => auth()->id(),
            ]);

            // Cierre de CxC por el monto aplicado
            MovimientoContable::create([
                'fecha' => $pago->fecha,
                'cuenta_puc' => '1305', 'tercero_type' => $terceroTipo, 'tercero_id' => $pago->contacto_id,
                'debe' => 0, 'haber' => $pago->monto_aplicado,
                'origen_type' => PagoVenta::class, 'origen_id' => $pago->id,
                'descripcion' => "Aplicación pago factura {$pago->factura?->numero}",
                'user_id' => auth()->id(),
            ]);

            // Si hay diferencia clasificada como descuento o flete, asiento adicional
            $movs = 2;
            $dif = (float) $pago->diferencia;
            if ($dif > 0 && $pago->clasificacion_diferencia) {
                $cuenta = match ($pago->clasificacion_diferencia->value) {
                    'descuento_pronto_pago', 'descuento_fuera_plazo' => '530510',
                    'flete_asumido_gb' => '5195',
                    default => null,
                };
                if ($cuenta) {
                    MovimientoContable::create([
                        'fecha' => $pago->fecha,
                        'cuenta_puc' => $cuenta, 'tercero_type' => $terceroTipo, 'tercero_id' => $pago->contacto_id,
                        'debe' => $dif, 'haber' => 0,
                        'origen_type' => PagoVenta::class, 'origen_id' => $pago->id,
                        'descripcion' => "Ajuste: " . $pago->clasificacion_diferencia->label(),
                        'user_id' => auth()->id(),
                    ]);
                    $movs++;
                }
            }

            // SOBREPAGO: si el cliente pagó de más, cuadramos la partida doble con 2805 anticipos
            // Antes: recibido(debe) > aplicado(haber) → descuadre. Ahora: haber 2805 = |dif|.
            $sobrepago = (float) $pago->monto_recibido - (float) $pago->monto_aplicado - max($dif, 0);
            if ($sobrepago > 0.009) {
                MovimientoContable::create([
                    'fecha' => $pago->fecha,
                    'cuenta_puc' => '2805', 'tercero_type' => $terceroTipo, 'tercero_id' => $pago->contacto_id,
                    'debe' => 0, 'haber' => $sobrepago,
                    'origen_type' => PagoVenta::class, 'origen_id' => $pago->id,
                    'descripcion' => "Sobrepago cliente — anticipo a favor por \${$sobrepago}",
                    'user_id' => auth()->id(),
                ]);
                $movs++;
            }

            return $movs;
        });
    }

    protected function limpiarPrevios($modelo): void
    {
        MovimientoContable::query()
            ->where('origen_type', get_class($modelo))
            ->where('origen_id', $modelo->id)
            ->delete();
    }
}
