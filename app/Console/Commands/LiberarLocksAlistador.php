<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Models\DropiAlistadorLock;
use Illuminate\Console\Command;

/**
 * §6 diseño Dropi — libera locks del alistador vencidos (heartbeat > 10 min).
 * Programar en app/Console/Kernel.php cada 2 minutos.
 */
class LiberarLocksAlistador extends Command
{
    protected $signature = 'dropi:liberar-locks {--minutos=10}';

    protected $description = 'Libera locks de alistador con heartbeat mayor al umbral.';

    public function handle(): int
    {
        $eliminados = DropiAlistadorLock::query()
            ->where('heartbeat_at', '<', now()->subMinutes((int) $this->option('minutos')))
            ->delete();

        $this->info("Locks liberados: {$eliminados}");

        return self::SUCCESS;
    }
}
