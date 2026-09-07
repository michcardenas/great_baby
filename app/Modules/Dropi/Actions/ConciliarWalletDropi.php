<?php

namespace App\Modules\Dropi\Actions;

use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\DTOs\PagoWalletDTO;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use App\Modules\Dropi\Models\DropiEstadoBitacora;
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
        // Idempotencia por ID único de Dropi (no por monto/fecha que colisionan).
        if ($mov->dropiMovimientoId
            && DropiWalletMovimiento::where('dropi_movimiento_id', $mov->dropiMovimientoId)->exists()
        ) {
            return 'duplicado';
        }

        $pedido = $mov->guia ? DropiPedido::where('guia', $mov->guia)->first() : null;

        DropiWalletMovimiento::create([
            'dropi_movimiento_id' => $mov->dropiMovimientoId,
            'fecha' => $mov->fecha->toDateString(),
            'tipo' => $mov->tipo,
            'monto' => $mov->monto,
            'pedido_id' => $pedido?->id,
            'categoria' => $mov->categoria,
            'fuente' => $mov->raw,
        ]);

        // §15 Capa 1: pago de guía → matchear + marcar pedido pagado
        if ($mov->tipo === 'pago_guia' && $pedido) {
            $anterior = $pedido->estado->value;
            $pedido->update([
                'estado' => EstadoPedidoDropi::Pagado,
                'pagado_at' => $mov->fecha,
            ]);

            DropiEstadoBitacora::create([
                'pedido_id' => $pedido->id,
                'estado_desde' => $anterior,
                'estado_hasta' => EstadoPedidoDropi::Pagado->value,
                'fuente' => 'sistema',
                'payload' => ['conciliacion' => 'auto', 'monto' => $mov->monto],
                'user_id' => auth()->id(),
            ]);

            // §19 detectar sanción por diferencia de precio
            $esperado = (float) $pedido->monto_esperado_proveedor;
            if ($mov->monto + 0.01 < $esperado) {
                DropiSancion::create([
                    'pedido_id' => $pedido->id,
                    'tipo' => 'diferencia_precio',
                    'monto_esperado' => $esperado,
                    'monto_recibido' => $mov->monto,
                    'diferencia' => $esperado - $mov->monto,
                    'detectada_at' => now(),
                ]);

                return 'sancion';
            }

            return 'pago';
        }

        // §19 sanción por categoría explícita (indemnización con guía)
        if (in_array($mov->tipo, ['indemnizacion'], true)) {
            if ($pedido) {
                DropiSancion::create([
                    'pedido_id' => $pedido->id,
                    'tipo' => 'categoria_explicita',
                    'monto_esperado' => 0,
                    'monto_recibido' => $mov->monto,
                    'diferencia' => abs($mov->monto),
                    'detectada_at' => now(),
                ]);

                return 'sancion';
            }

            return 'gasto';
        }

        // §18 otros gastos (flete garantía, tarjeta) o retiros
        return 'gasto';
    }
}
