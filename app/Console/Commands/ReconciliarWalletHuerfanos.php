<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiSancion;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * F15 · Re-matcheo periódico de movimientos wallet huérfanos con pedidos que
 * sincronizaron después. Si un `pago_guia` llegó a wallet antes de que el
 * pedido apareciera en el sync, quedó `pedido_id = NULL`.
 *
 * Re-audit H2 (func) / H3 (datos) · No basta con linkear el pedido: hay que
 * aplicar la MISMA lógica que ConciliarWalletDropi::procesar:
 *   - pedido en Devuelto/Cancelado → sanción `pago_sobre_devuelto`
 *   - pedido normal → transicionar a Pagado (sistema) + sanción `diferencia_precio` si aplica
 *
 * Programado en Kernel cada 30 min con withoutOverlapping.
 */
class ReconciliarWalletHuerfanos extends Command
{
    protected $signature = 'dropi:reconciliar-huerfanos {--limit=500}';

    protected $description = 'Re-matchea movimientos wallet huérfanos con pedidos que llegaron después + aplica lógica de conciliación.';

    public function handle(): int
    {
        $limite = (int) $this->option('limit');

        $huerfanos = DropiWalletMovimiento::query()
            ->whereNull('pedido_id')
            ->orderByDesc('fecha')
            ->limit($limite)
            ->get();

        $matched = 0;
        $marcadosPagado = 0;
        $sanciones = 0;
        $errores = 0;

        foreach ($huerfanos as $mov) {
            $fuente = is_array($mov->fuente) ? $mov->fuente : [];
            $guia = isset($fuente['guia']) ? strtoupper(trim((string) $fuente['guia'])) : null;
            if (! $guia) continue;

            $pedido = DropiPedido::where('guia', $guia)->first();
            if (! $pedido) continue;

            try {
                DB::transaction(function () use ($mov, $pedido, &$matched, &$marcadosPagado, &$sanciones) {
                    $mov->update(['pedido_id' => $pedido->id]);
                    $matched++;

                    $tipo = $mov->tipo instanceof \App\Modules\Dropi\Enums\TipoMovimientoWallet
                        ? $mov->tipo->value
                        : (string) $mov->tipo;

                    if ($tipo !== 'pago_guia') return;

                    // Pago sobre devuelto/cancelado → sanción, no marcar Pagado.
                    if (in_array($pedido->estado, [
                        EstadoPedidoDropi::Devuelto,
                        EstadoPedidoDropi::CanceladoDropi,
                        EstadoPedidoDropi::CanceladoGb,
                    ], true)) {
                        DropiSancion::updateOrCreate(
                            ['pedido_id' => $pedido->id, 'tipo' => 'pago_sobre_devuelto'],
                            [
                                'monto_esperado' => 0,
                                'monto_recibido' => $mov->monto,
                                'diferencia' => $mov->monto,
                                'detectada_at' => now(),
                            ],
                        );
                        $sanciones++;
                        return;
                    }

                    // Pedido normal — marcar Pagado si no lo está ya.
                    if ($pedido->estado !== EstadoPedidoDropi::Pagado) {
                        $pedido->transicionar(
                            EstadoPedidoDropi::Pagado,
                            'sistema',
                            null,
                            ['origen' => 'huerfano_rematch', 'monto' => (float) $mov->monto],
                            ['pagado_at' => $mov->fecha],
                        );
                        $marcadosPagado++;
                    }

                    // Diferencia de precio.
                    $esperado = (float) $pedido->monto_esperado_proveedor;
                    if ((float) $mov->monto + 0.01 < $esperado) {
                        DropiSancion::updateOrCreate(
                            ['pedido_id' => $pedido->id, 'tipo' => 'diferencia_precio'],
                            [
                                'monto_esperado' => $esperado,
                                'monto_recibido' => $mov->monto,
                                'diferencia' => $esperado - $mov->monto,
                                'detectada_at' => now(),
                            ],
                        );
                        $sanciones++;
                    }
                });
            } catch (\Throwable $e) {
                $errores++;
                Log::warning('dropi.reconciliar_huerfano_error', [
                    'mov_id' => $mov->id, 'guia' => $guia, 'msg' => $e->getMessage(),
                ]);
            }
        }

        $this->info(sprintf(
            'Huérfanos: %d · matcheados: %d · marcados Pagado: %d · sanciones: %d · errores: %d',
            $huerfanos->count(), $matched, $marcadosPagado, $sanciones, $errores,
        ));

        return self::SUCCESS;
    }
}
