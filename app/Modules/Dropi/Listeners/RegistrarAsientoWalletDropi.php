<?php

namespace App\Modules\Dropi\Listeners;

use App\Modules\Cartera\Models\MovimientoContable;
use App\Modules\Dropi\Enums\TipoMovimientoWallet;
use App\Modules\Dropi\Models\DropiWalletMovimiento;
use Illuminate\Database\Eloquent\Events\Created;
use Illuminate\Support\Facades\DB;

/**
 * P4 · Cada movimiento wallet Dropi genera un asiento contable de partida doble.
 * Idempotente por (origen_type, origen_id): la limpieza previa borra asientos
 * pasados del mismo movimiento antes de recrearlos.
 *
 * Cuentas PUC usadas:
 *   111015 · Wallet Dropi (sub-analítica de bancos)
 *   1305   · CxC Dropi por guía cobrada
 *   1110   · Bancos GB (retiros)
 *   4295   · Otros ingresos (indemnizaciones)
 *   5195   · Gastos de flete/transporte
 *   530525 · Comisiones bancarias/tarjetas
 *   5395   · Otros gastos operacionales (sanciones)
 */
class RegistrarAsientoWalletDropi
{
    public const CUENTA_WALLET = '111015';

    public function handle(DropiWalletMovimiento $mov): void
    {
        DB::transaction(function () use ($mov) {
            // Re-audit DR-θ (FUNC-M2) · antes: HARD-DELETE de asientos previos
            //   → si el mov cambió tras cerrar contabilidad del periodo, el
            //   libro mayor se corrompía sin traza. Ahora: si ya existe
            //   asiento previo para este movimiento, generamos AJUSTE INVERSO
            //   (contra-partida) y luego el nuevo. Nunca borrar historial.
            $previos = MovimientoContable::query()
                ->where('origen_type', DropiWalletMovimiento::class)
                ->where('origen_id', $mov->id)
                ->orderBy('id')
                ->get();

            if ($previos->isNotEmpty()) {
                foreach ($previos as $p) {
                    // No re-invertir asientos que ya fueron reversados (marca en descripcion).
                    if (str_starts_with((string) $p->descripcion, '[REVERSA]')) continue;
                    // Chequeo idempotente: si ya existe la reversa de este id, saltar.
                    $yaReversado = MovimientoContable::query()
                        ->where('origen_type', DropiWalletMovimiento::class)
                        ->where('origen_id', $mov->id)
                        ->where('descripcion', 'LIKE', '[REVERSA #' . $p->id . ']%')
                        ->exists();
                    if ($yaReversado) continue;

                    MovimientoContable::create([
                        'fecha' => now(),
                        'cuenta_puc' => $p->cuenta_puc,
                        'debe' => (float) $p->haber,   // invertido
                        'haber' => (float) $p->debe,   // invertido
                        'origen_type' => DropiWalletMovimiento::class,
                        'origen_id' => $mov->id,
                        'descripcion' => '[REVERSA #' . $p->id . '] ' . ($p->descripcion ?? ''),
                        'user_id' => auth()->id(),
                    ]);
                }
            }

            $tipo = $mov->tipo instanceof TipoMovimientoWallet
                ? $mov->tipo
                : TipoMovimientoWallet::tryFrom((string) $mov->tipo);
            if (! $tipo) return;

            $fecha = $mov->fecha;
            $monto = (float) $mov->monto;

            [$debe, $haber, $desc] = $this->cuentasPara($tipo, $monto, $mov);

            if (! $debe || ! $haber) return;

            MovimientoContable::create([
                'fecha' => $fecha,
                'cuenta_puc' => $debe,
                'tercero_type' => null, 'tercero_id' => null,
                'debe' => abs($monto), 'haber' => 0,
                'origen_type' => DropiWalletMovimiento::class,
                'origen_id' => $mov->id,
                'descripcion' => $desc,
                // N5 seg · en contexto de cron auth()->id() es null. Preferimos
                // user_id del payload `fuente` (Aracely en ajuste manual) o null
                // marcado como 'sistema' para que la auditoría muestre origen claro.
                'user_id' => is_array($mov->fuente) ? ($mov->fuente['user_id'] ?? null) : null,
            ]);
            MovimientoContable::create([
                'fecha' => $fecha,
                'cuenta_puc' => $haber,
                'tercero_type' => null, 'tercero_id' => null,
                'debe' => 0, 'haber' => abs($monto),
                'origen_type' => DropiWalletMovimiento::class,
                'origen_id' => $mov->id,
                'descripcion' => $desc,
                // N5 seg · en contexto de cron auth()->id() es null. Preferimos
                // user_id del payload `fuente` (Aracely en ajuste manual) o null
                // marcado como 'sistema' para que la auditoría muestre origen claro.
                'user_id' => is_array($mov->fuente) ? ($mov->fuente['user_id'] ?? null) : null,
            ]);
        });
    }

    /**
     * @return array{0:string,1:string,2:string}  [cuentaDebe, cuentaHaber, descripcion]
     */
    protected function cuentasPara(TipoMovimientoWallet $tipo, float $monto, DropiWalletMovimiento $mov): array
    {
        $ref = $mov->pedido?->guia ? "guía {$mov->pedido->guia}" : 'wallet Dropi';

        return match ($tipo) {
            // Ingreso del cliente final vía Dropi → aumenta wallet, cancela CxC.
            TipoMovimientoWallet::PagoGuia => [self::CUENTA_WALLET, '1305', "Pago wallet Dropi — {$ref}"],
            // Retiro a banco físico: sale de wallet, entra a bancos.
            TipoMovimientoWallet::RetiroBanco => ['1110', self::CUENTA_WALLET, 'Retiro wallet Dropi → banco GB'],
            // Indemnización: ingreso extraordinario.
            TipoMovimientoWallet::Indemnizacion => [self::CUENTA_WALLET, '4295', "Indemnización Dropi — {$ref}"],
            // Flete garantía: gasto operativo.
            TipoMovimientoWallet::FleteGarantia => ['5195', self::CUENTA_WALLET, "Flete garantía Dropi — {$ref}"],
            // Comisión tarjeta: gasto financiero.
            TipoMovimientoWallet::Tarjeta => ['530525', self::CUENTA_WALLET, "Comisión tarjeta Dropi — {$ref}"],
            // Otro: sin asiento por ahora (queda solo el registro wallet).
            TipoMovimientoWallet::Otro => ['', '', ''],
        };
    }
}
