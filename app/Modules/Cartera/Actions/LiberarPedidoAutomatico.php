<?php

namespace App\Modules\Cartera\Actions;

use App\Modules\Cartera\Models\SolicitudCredito;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §7 TO-BE Cartera — Motor de liberación automática de pedidos.
 * Reglas (en orden):
 *  1. Sin condición vigente → RETENER (Cartera)
 *  2. Mora crítica (>90 días) → RETENER (Gerencia)
 *  3. Tiene factura(s) vencida(s) → RETENER (Gerencia)  [regla reunión 16-sep,
 *     configurable con setting 'credito.bloquear_por_factura_vencida']
 *  4. Excede cupo disponible → RETENER (Gerencia si > cupo×2, si no Cartera)
 *  5. Si nada aplica → LIBERAR
 *
 * @return array{estado: 'liberado'|'retenido', motivo: string, nivel: string, credito: array, solicitud_id: ?int}
 */
class LiberarPedidoAutomatico
{
    use AsAction;

    public function handle(int $contactoId, float $montoPedido, bool $crearSolicitud = true, ?int $pedidoId = null): array
    {
        $credito = ConsultarCredito::run($contactoId);
        $motivo = null;
        $nivelSugerido = 'cartera';

        $bloquearPorVencida = (bool) setting('credito.bloquear_por_factura_vencida', true);

        if (! $credito['condicion_vigente']) {
            $motivo = 'Sin condición de crédito vigente';
        } elseif ($credito['tiene_mora_critica']) {
            $motivo = "Mora crítica ({$credito['dias_mora_max']} días > 90)";
            $nivelSugerido = 'gerencia';
        } elseif ($bloquearPorVencida && ($credito['tiene_factura_vencida'] ?? false)) {
            $n = $credito['facturas_vencidas'];
            $motivo = "Tiene {$n} factura(s) vencida(s) (mora máx. {$credito['dias_mora_max']} días)";
            $nivelSugerido = 'gerencia';
        } elseif ($montoPedido > $credito['disponible']) {
            $motivo = "Excede cupo disponible ($" . number_format($credito['disponible'], 0, ',', '.') . ")";
            $nivelSugerido = $montoPedido > ($credito['cupo'] * 2) ? 'gerencia' : 'cartera';
        }

        if ($motivo === null) {
            return ['estado' => 'liberado', 'motivo' => 'Dentro de cupo y sin mora crítica', 'nivel' => 'cartera', 'credito' => $credito, 'solicitud_id' => null];
        }

        $solicitud = null;
        if ($crearSolicitud) {
            $solicitud = SolicitudCredito::create([
                'contacto_id' => $contactoId,
                'pedido_id' => $pedidoId,
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
            'nivel' => $nivelSugerido,
            'credito' => $credito,
            'solicitud_id' => $solicitud?->id,
        ];
    }
}
