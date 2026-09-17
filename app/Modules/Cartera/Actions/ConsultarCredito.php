<?php

namespace App\Modules\Cartera\Actions;

use App\Models\Contacto;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\FacturaVenta;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §7 TO-BE Cartera — Consulta de crédito.
 * Devuelve para un contacto: cupo, saldo actual, disponible, mora crítica (>90 días).
 * Base para el motor de liberación automática.
 */
class ConsultarCredito
{
    use AsAction;

    /**
     * @return array{
     *   contacto_id:int,
     *   cupo:float,
     *   saldo_cartera:float,
     *   disponible:float,
     *   tiene_mora_critica:bool,
     *   dias_mora_max:int,
     *   facturas_vencidas:int,
     *   tiene_factura_vencida:bool,
     *   condicion_vigente:bool,
     * }
     */
    public function handle(int $contactoId): array
    {
        $contacto = Contacto::with('condicionVigente')->findOrFail($contactoId);
        $cupo = (float) ($contacto->condicionVigente?->cupo ?? 0);

        $facturas = FacturaVenta::query()
            ->where('contacto_id', $contactoId)
            ->whereNotIn('estado', [EstadoFactura::Pagada, EstadoFactura::Anulada])
            ->get();

        $saldo = (float) $facturas->sum('saldo');
        $moraMax = 0;
        $tieneMoraCritica = false;
        $facturasVencidas = 0;

        foreach ($facturas as $f) {
            $mora = $f->diasMora();
            if ($mora > $moraMax) $moraMax = $mora;
            if ($mora > 90) $tieneMoraCritica = true;
            if ($mora > 0 && (float) $f->saldo > 0) $facturasVencidas++;
        }

        return [
            'contacto_id' => $contactoId,
            'cupo' => $cupo,
            'saldo_cartera' => $saldo,
            'disponible' => max(0, $cupo - $saldo),
            'tiene_mora_critica' => $tieneMoraCritica,
            'dias_mora_max' => $moraMax,
            'facturas_vencidas' => $facturasVencidas,
            'tiene_factura_vencida' => $facturasVencidas > 0,
            'condicion_vigente' => (bool) $contacto->condicionVigente,
        ];
    }
}
