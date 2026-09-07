<?php

namespace App\Console\Commands;

use App\Modules\Inventario\Actions\LiberarReservasExpiradas;
use App\Modules\Inventario\Actions\VerificarAlertasStock;
use Illuminate\Console\Command;

class InventarioVerificarAlertas extends Command
{
    protected $signature = 'inventario:barrer';

    protected $description = 'Verifica alertas de stock y libera reservas expiradas.';

    public function handle(): int
    {
        $liberadas = LiberarReservasExpiradas::run();
        $this->info("Reservas expiradas liberadas: {$liberadas}");

        $disparadas = VerificarAlertasStock::run();
        $this->info("Alertas nuevas disparadas: {$disparadas}");

        return self::SUCCESS;
    }
}
