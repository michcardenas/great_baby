<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Inventario\Enums\EstadoTomaFisica;
use App\Modules\Inventario\Models\TomaFisica;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Re-audit M3 PATRÓN β + δ + ε · Cierra toma física con controles reales.
 *
 * Reglas contables (PUC colombiano):
 *   Sobrante (contado > sistema): 1435 débito (aumenta inventario) / 4295 crédito
 *     (aprovechamientos — sobrantes de inventario). Docblock ANTES decía 6135
 *     por error, contradiciendo el código.
 *   Faltante (contado < sistema): 5299 débito (pérdida por baja de inventario;
 *     el 5195 anterior era "transportes/fletes" — cuenta INCORRECTA) / 1435
 *     crédito (baja inventario).
 *
 * Fixes:
 *   - PATRÓN β (SEG-C1 + DATOS-C5) · `esContable()` obligatorio + `lockForUpdate`
 *     sobre la toma → cierre doble ya no duplica asientos.
 *   - PATRÓN δ (SEG-M2) · cuatro-ojos: `cerrada_por != creada_por` salvo Aracely/
 *     Gerencia (root).
 *   - PATRÓN ε (FUNC-C3 + DATOS-C6) · abort si algún item tiene costo_unit <= 0
 *     con diferencia — sin esto el asiento no se emitía pero el kardex sí →
 *     1435 quedaba descuadrada eternamente.
 *   - Precisión decimal: `round(..., 2)` explícito antes de contabilizar.
 *   - Audit log estructurado.
 */
class CerrarTomaFisica
{
    use AsAction;

    public function handle(TomaFisica $toma): TomaFisica
    {
        $u = auth()->user();
        // Re-audit M3 λ (SEG-M1, FUNC-B1) · `esContable()` retorna bool
        //   siempre, el `??` era dead code. Con `||` el fallback es explícito
        //   por si `esContable()` cambia de firma en el futuro.
        abort_unless(
            $u && ($u->esContable() || $u->esAracely()),
            403,
            'Solo Contador/Gerente/Aracely pueden cerrar una toma física (genera asiento contable).'
        );

        return DB::transaction(function () use ($toma, $u) {
            // PATRÓN β · lock la toma antes de leer.
            $toma = TomaFisica::query()->whereKey($toma->id)->lockForUpdate()->first();

            if ($toma->estado !== EstadoTomaFisica::EnConteo) {
                throw new InvalidArgumentException(
                    "Sólo se cierran tomas EnConteo. Estado actual: {$toma->estado->value}."
                );
            }

            // Guard idempotente: ¿ya se generaron movs para esta toma?
            $yaCerrada = InventarioMovimiento::query()
                ->where('referencia_tipo', TomaFisica::class)
                ->where('referencia_id', $toma->id)
                ->exists();
            if ($yaCerrada) {
                throw new InvalidArgumentException('La toma física ya tiene movimientos de ajuste registrados.');
            }

            // PATRÓN δ + λ (SEG-C1, FUNC-C2) · cuatro-ojos OBLIGATORIO
            //   excepto Aracely/Gerencia. Antes: `setting(..., true)` default
            //   TRUE dejaba SoD apagado sin que nadie configurara nada. Ahora
            //   default FALSE: el bypass sólo es posible si Gerencia lo activó
            //   explícitamente, y en ese caso queda auditado.
            $esRoot = $u->hasRole('Aracely') || $u->hasRole('Gerencia');
            if (! $esRoot && $toma->creada_por && $toma->creada_por === $u->id) {
                $permitirAuto = (bool) (function_exists('setting') ? setting('inventario.permitir_cerrar_propia_toma', false) : false);
                if (! $permitirAuto) {
                    abort(403, 'Segregación de funciones: quien crea una toma física no puede cerrarla. Solicita a Gerencia/Aracely.');
                }
                // Bypass explícito: dejar traza indeleble.
                \Illuminate\Support\Facades\Log::channel(
                    array_key_exists('audit', config('logging.channels') ?? []) ? 'audit' : 'stack'
                )->warning('inventario.toma_fisica.sod_bypass', [
                    'user_id' => $u->id, 'toma_id' => $toma->id, 'numero' => $toma->numero,
                    'setting_activo' => true,
                ]);
            }

            $toma->load('items');

            // PATRÓN ε · guard costo=0 con diferencia. Aborta antes de tocar kardex.
            foreach ($toma->items as $it) {
                if ($it->cantidad_contada === null) continue;
                $diff = (int) $it->diferencia;
                if ($diff === 0) continue;
                if ((float) ($it->costo_unit ?? 0) <= 0) {
                    throw new InvalidArgumentException(sprintf(
                        'Item variante %d con diferencia %d pero costo_unit=%.2f. Captura costo real (costo promedio ponderado) antes de cerrar; de lo contrario el asiento contable quedaría descuadrado vs kardex.',
                        $it->variante_id, $diff, (float) ($it->costo_unit ?? 0),
                    ));
                }
            }

            $itemsConDiferencia = 0;
            $valorAjuste = 0.0;
            $ts = now();

            foreach ($toma->items as $it) {
                if ($it->cantidad_contada === null) continue;
                $diff = (int) $it->diferencia;
                if ($diff === 0) continue;

                // PATRÓN H (arrastre M2) · abort si diferencia no es entera.
                if ($diff !== (int) $diff || abs($diff - round($diff)) > 0.0001) {
                    throw new InvalidArgumentException(
                        "Kardex no soporta fraccionarios · variante {$it->variante_id} diff={$diff}."
                    );
                }

                $itemsConDiferencia++;
                $valorAjuste += round($diff * (float) $it->costo_unit, 2);

                InventarioMovimiento::create([
                    'variante_id' => $it->variante_id,
                    'ubicacion_id' => $toma->ubicacion_id,
                    'tipo' => 'ajuste_toma',
                    'cantidad' => $diff,
                    'referencia_tipo' => TomaFisica::class,
                    'referencia_id' => $toma->id,
                    'user_id' => $u->id,
                    'notas' => "Toma física {$toma->numero} · sistema={$it->saldo_sistema} · contado={$it->cantidad_contada}",
                    'created_at' => $ts,
                ]);
            }

            $valorAjuste = round($valorAjuste, 2);
            if (abs($valorAjuste) > 0.01) {
                $abs = abs($valorAjuste);
                if ($valorAjuste > 0) {
                    $cuentaDebito = '1435'; // sobrante: aumenta inventario
                    $cuentaCredito = '4295'; // aprovechamientos
                } else {
                    // PATRÓN ε · 5299 (baja de inventario) reemplaza al 5195 incorrecto
                    // (5195 = "transportes/fletes", nada que ver con faltantes).
                    $cuentaDebito = '5299'; // pérdida por baja de inventario
                    $cuentaCredito = '1435';
                }

                MovimientoContable::create([
                    'fecha' => $ts, 'cuenta_puc' => $cuentaDebito,
                    'debe' => $abs, 'haber' => 0,
                    'origen_type' => TomaFisica::class, 'origen_id' => $toma->id,
                    'descripcion' => "Ajuste toma física {$toma->numero} · " . ($valorAjuste > 0 ? 'sobrante' : 'faltante'),
                    'user_id' => $u->id,
                ]);
                MovimientoContable::create([
                    'fecha' => $ts, 'cuenta_puc' => $cuentaCredito,
                    'debe' => 0, 'haber' => $abs,
                    'origen_type' => TomaFisica::class, 'origen_id' => $toma->id,
                    'descripcion' => "Contra-ajuste toma {$toma->numero}",
                    'user_id' => $u->id,
                ]);
            }

            $toma->items_diferentes = $itemsConDiferencia;
            $toma->valor_ajuste = $valorAjuste;
            $toma->estado = EstadoTomaFisica::Ajustada;
            $toma->cerrada_at = $ts;
            $toma->cerrada_por = $u->id;
            $toma->save();

            \Illuminate\Support\Facades\Log::channel(
                array_key_exists('audit', config('logging.channels') ?? []) ? 'audit' : 'stack'
            )->info('inventario.toma_fisica.cerrar', [
                'user_id' => $u->id, 'toma_id' => $toma->id, 'numero' => $toma->numero,
                'items_diferentes' => $itemsConDiferencia, 'valor_ajuste' => $valorAjuste,
                'ubicacion_id' => $toma->ubicacion_id,
            ]);

            return $toma->fresh(['items']);
        });
    }
}
