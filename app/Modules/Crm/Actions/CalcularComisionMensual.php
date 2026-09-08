<?php

namespace App\Modules\Crm\Actions;

use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\PagoVenta;
use App\Modules\Crm\Models\ComisionCalculada;
use App\Modules\Crm\Models\ComisionConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Calcula la comisión mensual para todos los vendedores con config activa.
 * Base = venta COBRADA en el mes (pagos aplicados) o venta FACTURADA según config.
 *
 * Idempotente: si ya existe un cálculo aprobado o pagado del mes, no lo reprocesa.
 */
class CalcularComisionMensual
{
    use AsAction;

    public function handle(int $anio, int $mes, ?int $vendedorId = null): array
    {
        $desde = Carbon::create($anio, $mes, 1)->startOfDay();
        $hasta = $desde->copy()->endOfMonth()->endOfDay();

        $configs = ComisionConfig::query()
            ->where('activo', true)
            ->when($vendedorId, fn ($q) => $q->where('vendedor_id', $vendedorId))
            ->with('vendedor')->get();

        $resultados = [];

        foreach ($configs as $config) {
            // No reprocesar si ya está aprobado/pagado
            $existente = ComisionCalculada::where('vendedor_id', $config->vendedor_id)
                ->where('anio', $anio)->where('mes', $mes)->first();
            if ($existente && in_array($existente->estado, ['aprobado', 'pagado'], true)) {
                $resultados[] = ['vendedor' => $config->vendedor->name, 'estado' => 'ya_cerrado'];
                continue;
            }

            $calc = $this->calcularParaVendedor($config, $anio, $mes, $desde, $hasta);
            $resultados[] = $calc;
        }

        return $resultados;
    }

    private function calcularParaVendedor(ComisionConfig $config, int $anio, int $mes, Carbon $desde, Carbon $hasta): array
    {
        // Facturas emitidas por este vendedor en el mes
        $facturas = FacturaVenta::query()
            ->where('vendedor_id', $config->vendedor_id)
            ->whereBetween('fecha_emision', [$desde, $hasta])
            ->whereNotIn('estado', [EstadoFactura::Anulada])
            ->get();

        $totalFacturado = (float) $facturas->sum('total');

        // Total COBRADO en el mes (pagos aplicados a facturas de este vendedor)
        // Solo cuenta pagos cuya fecha caiga en el mes, no la fecha de la factura
        $totalCobrado = 0;
        $detalle = [];
        foreach ($facturas as $f) {
            $cobradoEnMes = (float) PagoVenta::query()
                ->where('factura_id', $f->id)
                ->whereBetween('fecha', [$desde, $hasta])
                ->sum('monto_aplicado');
            $totalCobrado += $cobradoEnMes;
            if ($cobradoEnMes > 0 || $f->total > 0) {
                $detalle[] = [
                    'factura_id' => $f->id,
                    'numero' => $f->numero,
                    'total' => (float) $f->total,
                    'cobrado_en_mes' => $cobradoEnMes,
                ];
            }
        }

        $base = $config->cobra_solo_cobrado ? $totalCobrado : $totalFacturado;
        $comision = round($base * ($config->porcentaje_base / 100), 2);

        // Bono si cumple meta
        $bono = 0;
        if ($config->meta_mensual && $base >= (float) $config->meta_mensual && $config->bono_por_meta_pct) {
            $bono = round($base * ($config->bono_por_meta_pct / 100), 2);
        }

        $calc = ComisionCalculada::updateOrCreate(
            [
                'vendedor_id' => $config->vendedor_id,
                'anio' => $anio,
                'mes' => $mes,
            ],
            [
                'total_facturado' => $totalFacturado,
                'total_cobrado' => $totalCobrado,
                'base_comisionable' => $base,
                'porcentaje_aplicado' => $config->porcentaje_base,
                'comision' => $comision,
                'bono_meta' => $bono,
                'total_a_pagar' => $comision + $bono,
                'detalle_facturas' => $detalle,
                'estado' => 'borrador',
                'calculada_at' => now(),
            ]
        );

        return [
            'vendedor' => $config->vendedor->name,
            'estado' => 'ok',
            'facturado' => $totalFacturado,
            'cobrado' => $totalCobrado,
            'comision' => $calc->total_a_pagar,
        ];
    }
}
