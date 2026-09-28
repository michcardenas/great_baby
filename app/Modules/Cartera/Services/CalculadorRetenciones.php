<?php

namespace App\Modules\Cartera\Services;

use App\Modules\Cartera\Models\RetencionAplicada;
use App\Modules\Cartera\Models\RetencionConfig;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 4 · B.3 · Motor de cálculo de retenciones.
 *
 * Uso típico:
 *   $ret = app(CalculadorRetenciones::class)
 *       ->calcular(base: 1_000_000, concepto: 'compras_generales',
 *                  ciudad: 'Bogotá', iva: 190_000, granContribuyente: false);
 *   //   → [
 *   //       ['tipo' => 'retefuente', 'valor' => 25000, 'tarifa_pct' => 2.5, 'cuenta_puc' => '236540'],
 *   //       ['tipo' => 'reteica',    'valor' => 9660,  'tarifa_pct' => 9.66, 'cuenta_puc' => '236805'],
 *   //     ]
 *
 *   $calc->aplicar($facturaCompra, $retenciones);  // persiste en retenciones_aplicadas
 */
class CalculadorRetenciones
{
    /**
     * Devuelve las retenciones aplicables (sin persistir).
     *
     * @return array<int, array{tipo:string, config_id:int, base:float, tarifa_pct:float, valor:float, cuenta_puc:string}>
     */
    public function calcular(
        float $base,
        string $concepto,
        ?string $ciudad = null,
        float $iva = 0.0,
        bool $granContribuyente = false,
        bool $esAutorretenedor = false,
    ): array {
        $resultados = [];

        // 1. Retefuente por concepto (aplica siempre que la base > base_minima).
        //    QA-FIX #9 · Si el proveedor es autorretenedor, ÉL se retiene a sí mismo
        //    ante DIAN — no se le practica retefuente (doble retención = sanción).
        $rf = RetencionConfig::where('tipo', 'retefuente')
            ->where('activa', true)
            ->where('concepto', $concepto)
            ->first();
        if ($rf && $base >= (float) $rf->base_minima && ! $esAutorretenedor) {
            $valor = round($base * ((float) $rf->tarifa_pct / 100), 2);
            $resultados[] = [
                'tipo' => 'retefuente',
                'config_id' => $rf->id,
                'base' => $base,
                'tarifa_pct' => (float) $rf->tarifa_pct,
                'valor' => $valor,
                'cuenta_puc' => $rf->cuenta_puc,
            ];
        }

        // 2. Reteica por ciudad (si aplica).
        if ($ciudad) {
            $ri = RetencionConfig::where('tipo', 'reteica')
                ->where('activa', true)
                ->where('ciudad', $ciudad)
                ->where('concepto', $concepto)
                ->first();
            if ($ri && $base >= (float) $ri->base_minima) {
                // Reteica se expresa en x1000, pero usamos tarifa_pct: 9.66x1000 = 0.966%
                $valor = round($base * ((float) $ri->tarifa_pct / 100), 2);
                $resultados[] = [
                    'tipo' => 'reteica',
                    'config_id' => $ri->id,
                    'base' => $base,
                    'tarifa_pct' => (float) $ri->tarifa_pct,
                    'valor' => $valor,
                    'cuenta_puc' => $ri->cuenta_puc,
                ];
            }
        }

        // 3. Reteiva (solo si es gran contribuyente o auto-retenedor, y hay IVA).
        if ($granContribuyente && $iva > 0) {
            $riva = RetencionConfig::where('tipo', 'reteiva')
                ->where('activa', true)
                ->first();
            if ($riva) {
                $valor = round($iva * ((float) $riva->tarifa_pct / 100), 2);
                if ($valor > 0) {
                    $resultados[] = [
                        'tipo' => 'reteiva',
                        'config_id' => $riva->id,
                        'base' => $iva,
                        'tarifa_pct' => (float) $riva->tarifa_pct,
                        'valor' => $valor,
                        'cuenta_puc' => $riva->cuenta_puc,
                    ];
                }
            }
        }

        return $resultados;
    }

    /**
     * Persiste las retenciones calculadas contra un documento origen.
     *
     * @param  array<int, array<string, mixed>>  $retenciones  De ::calcular()
     * @return int  cantidad de retenciones aplicadas
     */
    public function aplicar(Model $origen, array $retenciones): int
    {
        $count = 0;
        foreach ($retenciones as $r) {
            RetencionAplicada::create(array_merge($r, [
                'origen_type' => $origen::class,
                'origen_id' => $origen->id,
            ]));
            $count++;
        }
        if ($count > 0) {
            Log::channel('single')->info('[Retenciones] aplicadas', [
                'origen' => class_basename($origen), 'id' => $origen->id, 'count' => $count,
            ]);
        }
        return $count;
    }

    /**
     * Total de retenciones aplicadas a un documento origen (util para restar del neto a pagar).
     */
    public function totalAplicado(Model $origen): float
    {
        return (float) RetencionAplicada::where('origen_type', $origen::class)
            ->where('origen_id', $origen->id)
            ->sum('valor');
    }
}
