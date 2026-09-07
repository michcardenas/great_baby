<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Models\SolicitudCredito;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §7 TO-BE Cartera — Motor de liberación automática de pedidos.
 * Reglas:
 *  1. Dentro del cupo disponible → LIBERAR
 *  2. Con mora crítica (>90 días) → RETENER (excepción a Cartera → Gerencia)
 *  3. Sin condición vigente → RETENER
 *
 * @return array{estado: 'liberado'|'retenido', motivo: string, credito: array}
 */
class LiberarPedidoAutomatico
{
    use AsAction;

    public function handle(int $contactoId, float $montoPedido, bool $crearSolicitud = true): array
    {
        $credito = ConsultarCredito::run($contactoId);
        $motivo = null;
        $nivelSugerido = 'cartera';

        if (! $credito['condicion_vigente']) {
            $motivo = 'Sin condición de crédito vigente';
        } elseif ($credito['tiene_mora_critica']) {
            $motivo = "Mora crítica ({$credito['dias_mora_max']} días > 90)";
            $nivelSugerido = 'gerencia';
        } elseif ($montoPedido > $credito['disponible']) {
            $motivo = "Excede cupo disponible ($" . number_format($credito['disponible'], 0, ',', '.') . ")";
            $nivelSugerido = $montoPedido > ($credito['cupo'] * 2) ? 'gerencia' : 'cartera';
        }

        if ($motivo === null) {
            return ['estado' => 'liberado', 'motivo' => 'Dentro de cupo y sin mora crítica', 'credito' => $credito];
        }

        $solicitud = null;
        if ($crearSolicitud) {
            $solicitud = SolicitudCredito::create([
                'contacto_id' => $contactoId,
                'monto_pedido' => $montoPedido,
                'motivo_retencion' => $motivo,
                'snapshot_credito' => $credito,
                'estado' => 'pendiente',
                'nivel_actual' => $nivelSugerido,
                'solicitada_por' => auth()->id(),
            ]);
        }

        return [
            'estado' => 'retenido',
            'motivo' => $motivo,
            'credito' => $credito,
            'solicitud_id' => $solicitud?->id,
        ];
    }
}
