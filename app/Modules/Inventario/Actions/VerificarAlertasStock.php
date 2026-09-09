<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Inventario\Models\AlertaStockDisparada;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Barre las configuraciones activas, dispara alertas nuevas cuando el saldo
 * cruza umbrales, y RESUELVE las alertas ya no vigentes.
 *
 * Re-audit M3 PATRÓN ζ (FUNC-A8 / DATOS-A15):
 *   - Antes NUNCA marcaba alertas como resueltas → UI saturada, alistador
 *     ignoraba todo por saturación.
 *   - Insertaba `ubicacion_id = $config->ubicacion_id ?? 0` → si la FK está
 *     activa violaba constraint. Ahora `null` cuando la config es global.
 *   - Chunk abortaba al primer UniqueViolation por race → propagaba excepción.
 *     Ahora try/catch por-alerta para no perder el barrido.
 *
 * Devuelve dict con contadores para observabilidad.
 * @return array{disparadas:int, resueltas:int, procesadas:int}
 */
class VerificarAlertasStock
{
    use AsAction;

    public function __construct(protected StockService $stock)
    {
    }

    public function handle(): array
    {
        $disparadas = 0;
        $resueltas = 0;
        $procesadas = 0;

        AlertaStockConfig::with('variante')
            ->where('activa', true)
            ->chunkById(100, function ($configs) use (&$disparadas, &$resueltas, &$procesadas) {
                foreach ($configs as $config) {
                    $procesadas++;
                    $ubicacionId = $config->ubicacion_id;
                    $saldo = $this->stock->saldoDisponible($config->variante_id, $ubicacionId);

                    // Evaluar cada tipo: si la condición se cumple → tratar de disparar.
                    //                    si NO se cumple → resolver alertas abiertas.
                    $condiciones = [
                        'minimo' => (bool) ($config->stock_minimo && $saldo < $config->stock_minimo),
                        'reorden' => (bool) ($config->punto_reorden && $saldo <= $config->punto_reorden),
                        'maximo' => (bool) ($config->stock_maximo && $saldo > $config->stock_maximo),
                    ];

                    foreach ($condiciones as $tipo => $activa) {
                        if ($activa) {
                            $resueltas += $this->intentarDisparar($config, $tipo, $saldo, $disparadas);
                        } else {
                            $resueltas += $this->resolverPorTipo($config, $tipo);
                        }
                    }
                }
            });

        return ['disparadas' => $disparadas, 'resueltas' => $resueltas, 'procesadas' => $procesadas];
    }

    /**
     * Dispara alerta si no hay una abierta. Idempotente por race (try/catch unique).
     */
    protected function intentarDisparar(AlertaStockConfig $config, string $tipo, int $saldo, int &$disparadas): int
    {
        $ya = AlertaStockDisparada::query()
            ->where('config_id', $config->id)
            ->where('tipo', $tipo)
            ->where('resuelta', false)
            ->exists();
        if ($ya) return 0;

        try {
            DB::transaction(function () use ($config, $tipo, $saldo) {
                AlertaStockDisparada::create([
                    'config_id' => $config->id,
                    'variante_id' => $config->variante_id,
                    // Ahora null si la config es global — antes se forzaba 0
                    // rompiendo FK potencial.
                    'ubicacion_id' => $config->ubicacion_id,
                    'tipo' => $tipo,
                    'saldo_al_disparar' => $saldo,
                    'resuelta' => false,
                ]);
            });
            $disparadas++;
        } catch (\Illuminate\Database\QueryException $e) {
            // Race con otro worker que insertó simultáneo (unique violation).
            // No perdemos el barrido: el registro ya existe → seguimos.
            if ((int) $e->getCode() === 23000 || (string) $e->getCode() === '23000') return 0;
            throw $e;
        }
        return 0;
    }

    /**
     * Marca resueltas las alertas del tipo dado cuya condición ya no aplica.
     * Usa iteración por instancia para que dispare Auditable (bulk update no).
     */
    protected function resolverPorTipo(AlertaStockConfig $config, string $tipo): int
    {
        $alertas = AlertaStockDisparada::query()
            ->where('config_id', $config->id)
            ->where('tipo', $tipo)
            ->where('resuelta', false)
            ->get();
        $n = 0;
        foreach ($alertas as $a) {
            $a->resuelta = true;
            $a->resuelta_at = now();
            $a->save();
            $n++;
        }
        return $n;
    }
}
