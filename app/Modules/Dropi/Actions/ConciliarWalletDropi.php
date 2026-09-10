<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\DTOs\PagoWalletDTO;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiSancion;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * §15 Diseño Dropi — Conciliación en 2 capas:
 *   Capa 1: guía ↔ wallet (match automático).
 *   Capa 2: wallet ↔ banco → NO se compara línea por línea (§17). Solo retiros consolidados.
 *
 * Comparamos lo recibido contra monto_esperado_proveedor del catálogo GB.
 * Si hay diferencia y viene sin categoría → registra sanción presunta (§19).
 * Si viene como "indemnizacion" → sanción confirmada por categoría explícita.
 */
class ConciliarWalletDropi
{
    use AsAction;

    public function __construct(protected DropiClientInterface $client) {}

    /**
     * @return array{procesados:int, pagos_matcheados:int, sanciones_detectadas:int, gastos_registrados:int}
     */
    public function handle(?CarbonImmutable $desde = null): array
    {
        $desde ??= CarbonImmutable::now()->subMonth();

        $movimientos = $this->client->walletDesde($desde);

        $pagos = 0;
        $sanciones = 0;
        $gastos = 0;

        foreach ($movimientos as $mov) {
            $resultado = DB::transaction(fn () => $this->procesar($mov));

            match ($resultado) {
                'pago' => $pagos++,
                'sancion' => $sanciones++,
                'gasto' => $gastos++,
                default => null,
            };
        }

        return [
            'procesados' => $movimientos->count(),
            'pagos_matcheados' => $pagos,
            'sanciones_detectadas' => $sanciones,
            'gastos_registrados' => $gastos,
        ];
    }

    protected function procesar(PagoWalletDTO $mov): string
    {
        // P7 · Idempotencia por ID único. Si el DTO no trae uno, sintetizar
        // determinístico para que la misma fila no se duplique en re-corridas.
        $movId = $mov->dropiMovimientoId ?: ('synth:' . hash('sha256', implode('|', [
            $mov->fecha->toDateString(), $mov->tipo, (string) $mov->monto,
            (string) ($mov->guia ?? ''), (string) ($mov->categoria ?? ''),
        ])));

        if (DropiWalletMovimiento::where('dropi_movimiento_id', $movId)->exists()) {
            return 'duplicado';
        }

        $guiaNormalizada = $mov->guia ? strtoupper(trim($mov->guia)) : null;
        $pedido = $guiaNormalizada ? DropiPedido::where('guia', $guiaNormalizada)->first() : null;

        DropiWalletMovimiento::create([
            'dropi_movimiento_id' => $movId,
            'fecha' => $mov->fecha->toDateString(),
            'tipo' => $mov->tipo,
            'monto' => $mov->monto,
            'pedido_id' => $pedido?->id,
            'categoria' => $mov->categoria,
            'fuente' => $mov->raw,
        ]);

        // §15 Capa 1: pago de guía → matchear + marcar pedido pagado
        if ($mov->tipo === 'pago_guia' && $pedido) {
            // A6 · si el pedido fue devuelto o cancelado, NO se marca Pagado.
            // En su lugar se registra sanción tipo pago_sobre_devuelto.
            if (in_array($pedido->estado, [
                EstadoPedidoDropi::Devuelto,
                EstadoPedidoDropi::CanceladoDropi,
                EstadoPedidoDropi::CanceladoGb,
            ], true)) {
                // H8 func / H2 datos · UNIQUE(pedido_id, tipo) + create desnudo
                // reventaba en re-conciliación → updateOrCreate mantiene idempotencia.
                DropiSancion::updateOrCreate(
                    ['pedido_id' => $pedido->id, 'tipo' => 'pago_sobre_devuelto'],
                    [
                        'monto_esperado' => 0,
                        'monto_recibido' => $mov->monto,
                        'diferencia' => $mov->monto,
                        'detectada_at' => now(),
                    ],
                );

                return 'sancion';
            }

            $pedido->transicionar(
                EstadoPedidoDropi::Pagado,
                'sistema',
                auth()->id(),
                ['conciliacion' => 'auto', 'monto' => $mov->monto],
                ['pagado_at' => $mov->fecha],
            );

            // §19 detectar diferencia de precio (subpago O sobrepago).
            //
            // Re-audit DR-θ (FUNC-M1) · antes solo detectaba subpagos
            //   (`$mov->monto + 0.01 < $esperado`). Sobrepagos de Dropi pasaban
            //   silenciosos, distorsionando reportes. Ahora se registra ambos
            //   como diferencia; el signo lo lleva `diferencia`:
            //     + = subpago (esperado > recibido) — Dropi debe
            //     - = sobrepago (recibido > esperado) — GB debe devolver
            $esperado = (float) $pedido->monto_esperado_proveedor;
            $delta = $esperado - (float) $mov->monto; // + = subpago
            if (abs($delta) > 0.01) {
                DropiSancion::updateOrCreate(
                    ['pedido_id' => $pedido->id, 'tipo' => $delta > 0 ? 'diferencia_precio' : 'sobrepago'],
                    [
                        'monto_esperado' => $esperado,
                        'monto_recibido' => $mov->monto,
                        'diferencia' => $delta, // firmado
                        'detectada_at' => now(),
                    ],
                );

                return 'sancion';
            }

            return 'pago';
        }

        // §19 sanción por categoría explícita (indemnización con guía)
        if (in_array($mov->tipo, ['indemnizacion'], true)) {
            if ($pedido) {
                DropiSancion::updateOrCreate(
                    ['pedido_id' => $pedido->id, 'tipo' => 'categoria_explicita'],
                    [
                        'monto_esperado' => 0,
                        'monto_recibido' => $mov->monto,
                        'diferencia' => abs($mov->monto),
                        'detectada_at' => now(),
                    ],
                );

                return 'sancion';
            }

            return 'gasto';
        }

        // §18 otros gastos (flete garantía, tarjeta) o retiros
        return 'gasto';
    }
}
