<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Actions\SincronizarPedidosDropi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SincronizarDropiCommand extends Command
{
    protected $signature = 'dropi:sync {--horas=24 : Traer pedidos creados en las últimas N horas}';

    protected $description = 'Sincroniza pedidos y estados de guías desde Dropi al ERP.';

    public function handle(SincronizarPedidosDropi $action): int
    {
        $desde = CarbonImmutable::now()->subHours((int) $this->option('horas'));
        $this->info("Sincronizando pedidos Dropi desde {$desde->format('Y-m-d H:i')}...");

        $r = $action->handle($desde);

        $this->table(
            ['Driver', 'Procesados', 'Nuevos', 'Actualizados', 'Pend. inv.'],
            [[$r['driver'], $r['procesados'], $r['nuevos'], $r['actualizados'], $r['pendientes_inv']]],
        );

        return self::SUCCESS;
    }
}
