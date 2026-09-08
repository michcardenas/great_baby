<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\EmpaqueRegistro;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Procesa un escaneo desde la pistola de la estación de empaque.
 * Retorna un resultado tipado con qué hacer en el UI: abrir pedido, marcar item, error.
 */
class ProcesarEscaneoEmpaque
{
    use AsAction;

    /**
     * @return array{
     *   tipo: 'pedido_abierto'|'variante_marcada'|'pedido_no_encontrado'|'variante_sin_pedido'|'variante_ya_marcada'|'error',
     *   mensaje: string,
     *   pedido_id?: int,
     *   variante_id?: int,
     *   sonido: 'ok'|'warn'|'error',
     * }
     */
    public function handle(string $codigo, ?int $pedidoActivoId, int $operarioId): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') {
            return ['tipo' => 'error', 'mensaje' => 'Código vacío', 'sonido' => 'error'];
        }

        return DB::transaction(function () use ($codigo, $pedidoActivoId, $operarioId) {
            // 1. ¿Es una guía de pedido?
            $pedido = DropiPedido::query()
                ->with(['items.variante.producto'])
                ->where(function ($q) use ($codigo) {
                    $q->where('guia', $codigo)->orWhere('dropi_orden_id', $codigo);
                })
                ->lockForUpdate()
                ->first();

            if ($pedido) {
                // Bloquear pedidos ya empacados/despachados/entregados/pagados (evita reapertura y duplicación de métricas).
                if (in_array($pedido->estado, [
                    EstadoPedidoDropi::Empacado,
                    EstadoPedidoDropi::Despachado,
                    EstadoPedidoDropi::Entregado,
                    EstadoPedidoDropi::Pagado,
                ], true)) {
                    return [
                        'tipo' => 'error',
                        'mensaje' => "Pedido {$codigo} ya fue empacado/despachado.",
                        'sonido' => 'error',
                    ];
                }

                // Rechazar guía sin ítems (empaque fantasma).
                $totalUnidades = (int) $pedido->items->sum(fn ($it) => (int) ($it->cantidad ?? 1));
                if ($totalUnidades === 0) {
                    return [
                        'tipo' => 'error',
                        'mensaje' => "Pedido {$codigo} no tiene ítems — reportá a Aracely",
                        'sonido' => 'error',
                    ];
                }

                // Bloqueo de carrera: si ya hay un registro en curso por otro operario, aviso
                $enCurso = EmpaqueRegistro::where('pedido_id', $pedido->id)
                    ->where('estado', 'en_curso')
                    ->lockForUpdate()
                    ->first();

                if ($enCurso && $enCurso->operario_id !== $operarioId) {
                    return [
                        'tipo' => 'error',
                        'mensaje' => "Pedido {$codigo} lo está empacando otro operario.",
                        'sonido' => 'error',
                    ];
                }

                // firstOrCreate para blindaje: aunque el lockForUpdate sobre dropi_pedidos
                // ya serializa, esto garantiza idempotencia si alguien bypassa el lock.
                EmpaqueRegistro::firstOrCreate(
                    ['pedido_id' => $pedido->id, 'estado' => 'en_curso'],
                    [
                        'operario_id' => $operarioId,
                        'inicio_at' => now(),
                        'items_totales' => $totalUnidades,
                        'items_escaneados' => 0,
                    ]
                );

                if ($pedido->estado !== EstadoPedidoDropi::Alistando) {
                    $pedido->estado = EstadoPedidoDropi::Alistando;
                    $pedido->save();
                }

                return [
                    'tipo' => 'pedido_abierto',
                    'mensaje' => "Pedido {$codigo} abierto — {$pedido->items->count()} ítems para empacar",
                    'pedido_id' => $pedido->id,
                    'sonido' => 'ok',
                ];
            }

            // 2. ¿Es un código de variante? (necesita pedido activo)
            $variante = ProductoVariante::where('codigo_barras', $codigo)->first();
            if (! $variante) {
                return [
                    'tipo' => 'error',
                    'mensaje' => "Código {$codigo} no reconocido. ¿Está bien impresa la etiqueta?",
                    'sonido' => 'error',
                ];
            }

            if (! $pedidoActivoId) {
                return [
                    'tipo' => 'variante_sin_pedido',
                    'mensaje' => 'Escaneaste una variante pero no hay pedido activo. Escanea primero la guía.',
                    'sonido' => 'warn',
                ];
            }

            $pedido = DropiPedido::with('items.variante')->lockForUpdate()->find($pedidoActivoId);

            // Ownership: si hay un registro en_curso de OTRO operario, bloquear
            $registroActivo = EmpaqueRegistro::where('pedido_id', $pedidoActivoId)
                ->where('estado', 'en_curso')
                ->first();
            if ($registroActivo && $registroActivo->operario_id !== $operarioId) {
                return [
                    'tipo' => 'error',
                    'mensaje' => "Otro operario está empacando este pedido.",
                    'sonido' => 'error',
                ];
            }

            $item = $pedido?->items->firstWhere('variante_id', $variante->id);

            if (! $item) {
                return [
                    'tipo' => 'error',
                    'mensaje' => "La variante {$codigo} no está en el pedido actual.",
                    'sonido' => 'error',
                ];
            }

            // Recargar el item con lock para evitar race en el increment.
            $itemLocked = \App\Modules\Dropi\Models\DropiPedidoItem::where('id', $item->id)
                ->lockForUpdate()->first();
            $cantidadRequerida = (int) ($itemLocked->cantidad ?? 1);
            $cantidadYa = (int) ($itemLocked->cantidad_pickeada ?? 0);

            if ($cantidadYa >= $cantidadRequerida) {
                return [
                    'tipo' => 'variante_ya_marcada',
                    'mensaje' => "{$variante->producto?->nombre} ya está completo ({$cantidadYa}/{$cantidadRequerida}).",
                    'variante_id' => $variante->id,
                    'sonido' => 'warn',
                ];
            }

            $itemLocked->cantidad_pickeada = $cantidadYa + 1;
            if ($itemLocked->cantidad_pickeada >= $cantidadRequerida) {
                $itemLocked->pickeado_at = now();
                $itemLocked->pickeado_por = $operarioId;
            }
            $itemLocked->save();
            $item = $itemLocked;

            EmpaqueRegistro::where('pedido_id', $pedido->id)
                ->where('estado', 'en_curso')
                ->where('operario_id', $operarioId)
                ->increment('items_escaneados');

            $restanteItem = $cantidadRequerida - $item->cantidad_pickeada;
            $sufijo = $restanteItem > 0 ? " (faltan {$restanteItem})" : '';

            return [
                'tipo' => 'variante_marcada',
                'mensaje' => "✓ {$variante->producto?->nombre} — {$variante->color_nombre}{$sufijo}",
                'variante_id' => $variante->id,
                'sonido' => 'ok',
            ];
        });
    }
}
