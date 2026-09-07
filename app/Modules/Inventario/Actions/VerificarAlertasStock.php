<?php

namespace App\Modules\Inventario\Actions;

use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Inventario\Models\AlertaStockDisparada;
use App\Modules\Inventario\Services\StockService;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Barre las configuraciones activas y dispara alertas nuevas cuando el saldo cruza umbrales.
 * Idempotente: si ya hay una alerta abierta del mismo tipo, no crea otra.
 */
class VerificarAlertasStock
{
    use AsAction;

    public function __construct(protected StockService $stock)
    {
    }

    public function handle(): int
    {
        $disparadas = 0;

        // Fix auditor #10: chunkById evita saltarse filas si otro proceso inserta configs.
        AlertaStockConfig::with('variante')
            ->where('activa', true)
            ->chunkById(100, function ($configs) use (&$disparadas) {
                foreach ($configs as $config) {
                    $ubicacionId = $config->ubicacion_id;
                    $saldo = $this->stock->saldoDisponible($config->variante_id, $ubicacionId);

                    $tipos = [];
                    if ($config->stock_minimo && $saldo < $config->stock_minimo) {
                        $tipos[] = 'minimo';
                    }
                    if ($config->punto_reorden && $saldo <= $config->punto_reorden) {
                        $tipos[] = 'reorden';
                    }
                    if ($config->stock_maximo && $saldo > $config->stock_maximo) {
                        $tipos[] = 'maximo';
                    }

                    foreach ($tipos as $tipo) {
                        $ya = AlertaStockDisparada::where('config_id', $config->id)
                            ->where('tipo', $tipo)
                            ->where('resuelta', false)
                            ->exists();
                        if ($ya) {
                            continue;
                        }
                        DB::transaction(function () use ($config, $tipo, $saldo) {
                            AlertaStockDisparada::create([
                                'config_id' => $config->id,
                                'variante_id' => $config->variante_id,
                                'ubicacion_id' => $config->ubicacion_id ?? 0,
                                'tipo' => $tipo,
                                'saldo_al_disparar' => $saldo,
                            ]);
                        });
                        $disparadas++;
                    }
                }
            });

        return $disparadas;
    }
}
