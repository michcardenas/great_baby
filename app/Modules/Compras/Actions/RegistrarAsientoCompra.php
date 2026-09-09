<?php

namespace App\Modules\Compras\Actions;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Compras\Models\Importacion;
use App\Modules\Compras\Models\RecepcionCompra;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * PUC Colombia:
 *   1435 Mercancía no fabricada por la empresa
 *   1465 Inventario en tránsito
 *   1355 Anticipo de impuestos (crédito IVA / retenciones a favor)
 *   2205 Proveedores nacionales
 *   2210 Proveedores del exterior
 *   2408 IVA descontable
 *   2365 Retención en la fuente
 *   2367 Retención de IVA
 *   2368 Reteica
 *   5195 Diversos (gastos no capitalizables)
 */
class RegistrarAsientoCompra
{
    use AsAction;

    public function recepcion(RecepcionCompra $recepcion): int
    {
        return DB::transaction(function () use ($recepcion) {
            $this->limpiar($recepcion);
            $orden = $recepcion->orden;
            $terceroTipo = 'App\\Models\\Contacto';
            $cuentaProveedor = $orden->esImportacion() ? '2210' : '2205';
            $cuentaInventario = $orden->esImportacion() ? '1465' : '1435';

            $totalRecibido = (float) $recepcion->items->sum('subtotal');
            if ($totalRecibido <= 0) {
                return 0;
            }

            // Re-audit M2 PATRÓN G (FUNC-A3) · en OC de importación el IVA
            // descontable NO se contabiliza en la recepción — se paga a la DIAN
            // vía agente aduanero y se registra en `liquidacion()` (1355). Antes
            // se duplicaba: 2408 en recepción + 1355 en liquidación.
            $ivaProporcion = 0;
            if (! $orden->esImportacion() && (float) $orden->subtotal > 0) {
                $ivaProporcion = (float) $orden->iva * ($totalRecibido / (float) $orden->subtotal);
            }

            $movs = 0;

            MovimientoContable::create([
                'fecha' => $recepcion->fecha_recepcion,
                'cuenta_puc' => $cuentaInventario,
                'tercero_type' => $terceroTipo, 'tercero_id' => $orden->proveedor_id,
                'debe' => round($totalRecibido, 2), 'haber' => 0,
                'origen_type' => RecepcionCompra::class, 'origen_id' => $recepcion->id,
                'descripcion' => "Recepción {$recepcion->numero} — OC {$orden->numero}",
                'user_id' => auth()->id(),
            ]);
            $movs++;

            if ($ivaProporcion > 0.01) {
                MovimientoContable::create([
                    'fecha' => $recepcion->fecha_recepcion,
                    'cuenta_puc' => '2408',
                    'tercero_type' => $terceroTipo, 'tercero_id' => $orden->proveedor_id,
                    'debe' => round($ivaProporcion, 2), 'haber' => 0,
                    'origen_type' => RecepcionCompra::class, 'origen_id' => $recepcion->id,
                    'descripcion' => "IVA descontable rec {$recepcion->numero}",
                    'user_id' => auth()->id(),
                ]);
                $movs++;
            }

            MovimientoContable::create([
                'fecha' => $recepcion->fecha_recepcion,
                'cuenta_puc' => $cuentaProveedor,
                'tercero_type' => $terceroTipo, 'tercero_id' => $orden->proveedor_id,
                'debe' => 0, 'haber' => round($totalRecibido + $ivaProporcion, 2),
                'origen_type' => RecepcionCompra::class, 'origen_id' => $recepcion->id,
                'descripcion' => "CxP a proveedor por rec {$recepcion->numero}",
                'user_id' => auth()->id(),
            ]);
            $movs++;

            return $movs;
        });
    }

    /**
     * Al liquidar una importación: mueve 1465 → 1435 con los gastos prorrateados
     * capitalizables sumados, y contra 5195 los no capitalizables.
     */
    public function liquidacion(Importacion $importacion): int
    {
        return DB::transaction(function () use ($importacion) {
            $this->limpiar($importacion);
            $terceroTipo = 'App\\Models\\Contacto';
            $fecha = $importacion->fecha_liquidacion ?? now();

            $costoFob = (float) $importacion->lineas->sum('costo_fob_total');
            $gastosCap = (float) $importacion->gastos->where('capitalizable', true)->sum('monto_base');
            $gastosNoCap = (float) $importacion->gastos->where('capitalizable', false)->sum('monto_base');

            $movs = 0;

            if ($costoFob + $gastosCap > 0) {
                MovimientoContable::create([
                    'fecha' => $fecha,
                    'cuenta_puc' => '1435',
                    'tercero_type' => null, 'tercero_id' => null,
                    'debe' => round($costoFob + $gastosCap, 2), 'haber' => 0,
                    'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                    'descripcion' => "Liquidación IMP {$importacion->numero}: inventario nacionalizado",
                    'user_id' => auth()->id(),
                ]);
                $movs++;

                MovimientoContable::create([
                    'fecha' => $fecha,
                    'cuenta_puc' => '1465',
                    'tercero_type' => null, 'tercero_id' => null,
                    'debe' => 0, 'haber' => round($costoFob, 2),
                    'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                    'descripcion' => "Salida inventario en tránsito IMP {$importacion->numero}",
                    'user_id' => auth()->id(),
                ]);
                $movs++;
            }

            foreach ($importacion->gastos as $gasto) {
                $monto = (float) $gasto->monto_base;

                // BUG AUDITOR #4: IVA importación es crédito fiscal (1355 debe), no gasto (5195).
                // Los capitalizables ya entraron a 1435 arriba, solo faltan los NO capitalizables.
                $isIvaImportacion = $gasto->concepto->value === 'iva_importacion';

                if ($isIvaImportacion) {
                    // 1355 debe (IVA a favor, activo) / 2205 haber (CxP al agente aduanero)
                    MovimientoContable::create([
                        'fecha' => $fecha,
                        'cuenta_puc' => '1355',
                        'tercero_type' => $gasto->proveedor_id ? $terceroTipo : null,
                        'tercero_id' => $gasto->proveedor_id,
                        'debe' => $monto, 'haber' => 0,
                        'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                        'descripcion' => "IVA importación: {$gasto->descripcion}",
                        'user_id' => auth()->id(),
                    ]);
                    MovimientoContable::create([
                        'fecha' => $fecha,
                        'cuenta_puc' => '2205',
                        'tercero_type' => $gasto->proveedor_id ? $terceroTipo : null,
                        'tercero_id' => $gasto->proveedor_id,
                        'debe' => 0, 'haber' => $monto,
                        'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                        'descripcion' => "CxP agente aduanero: {$gasto->descripcion}",
                        'user_id' => auth()->id(),
                    ]);
                    $movs += 2;
                    continue;
                }

                // Gastos no capitalizables van a gasto operacional (5195)
                if (! $gasto->capitalizable) {
                    MovimientoContable::create([
                        'fecha' => $fecha,
                        'cuenta_puc' => '5195',
                        'tercero_type' => $gasto->proveedor_id ? $terceroTipo : null,
                        'tercero_id' => $gasto->proveedor_id,
                        'debe' => $monto, 'haber' => 0,
                        'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                        'descripcion' => "Gasto no capitalizable: {$gasto->descripcion}",
                        'user_id' => auth()->id(),
                    ]);
                    MovimientoContable::create([
                        'fecha' => $fecha,
                        'cuenta_puc' => '2205',
                        'tercero_type' => $gasto->proveedor_id ? $terceroTipo : null,
                        'tercero_id' => $gasto->proveedor_id,
                        'debe' => 0, 'haber' => $monto,
                        'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                        'descripcion' => "CxP proveedor gasto: {$gasto->descripcion}",
                        'user_id' => auth()->id(),
                    ]);
                    $movs += 2;
                    continue;
                }

                // Capitalizables (flete, seguro, arancel, aduana): ya entraron a 1435, ahora CxP al proveedor del gasto
                MovimientoContable::create([
                    'fecha' => $fecha,
                    'cuenta_puc' => '2205',
                    'tercero_type' => $gasto->proveedor_id ? $terceroTipo : null,
                    'tercero_id' => $gasto->proveedor_id,
                    'debe' => 0, 'haber' => $monto,
                    'origen_type' => Importacion::class, 'origen_id' => $importacion->id,
                    'descripcion' => "CxP {$gasto->concepto->label()}: {$gasto->descripcion}",
                    'user_id' => auth()->id(),
                ]);
                $movs++;
            }

            return $movs;
        });
    }

    /**
     * Re-audit M2 PATRÓN D (DATOS-C7) · forceDelete físico, mismo criterio que
     * M4 Cartera y M5 Contabilidad. Antes `->delete()` soft-borraba dejando
     * shadow rows que hinchaban `audits` y `MovimientoContable::withTrashed()`
     * mostraba versiones contradictorias del mismo hecho. Un asiento previo
     * a la re-ejecución es basura — el libro DIAN vive en las OC/Importaciones
     * emitidas, no en asientos huérfanos.
     */
    protected function limpiar($modelo): void
    {
        MovimientoContable::query()
            ->where('origen_type', get_class($modelo))
            ->where('origen_id', $modelo->id)
            ->forceDelete();
    }
}
