<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Console\Command;

/**
 * B3-M5 · purga siigo_sync_log > N días (default 30).
 * Corre nocturno via schedule · sin esto la tabla crece ~150 MB/día.
 */
class PurgarSiigoSyncLogCommand extends Command
{
    protected $signature = 'siigo:purgar-logs {--dias=30 : Retención en días}';

    protected $description = 'Purga siigo_sync_log más viejos que N días (default 30)';

    public function handle(): int
    {
        $dias = max(1, (int) $this->option('dias'));
        $limite = now()->subDays($dias);

        $borrados = SiigoSyncLog::where('created_at', '<', $limite)->delete();

        $this->info("Purgados {$borrados} registros de siigo_sync_log (>{$dias} días).");
        return self::SUCCESS;
    }
}
