<?php

namespace App\Console\Commands;

use App\Modules\Dropi\Actions\SincronizarPedidosDropi;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SincronizarDropiCommand extends Command
{
    protected $signature = 'dropi:sync
        {--horas=24 : Traer pedidos creados en las últimas N horas (1..720)}
        {--force : Ignora el rate-limit interno (uso admin, con precaución)}';

    protected $description = 'Sincroniza pedidos y estados de guías desde Dropi al ERP.';

    public function handle(SincronizarPedidosDropi $action): int
    {
        // Re-audit DR-ι · validar el rango de horas para evitar sync gigantes
        //   accidentales (--horas=99999 desde cron mal configurado → pedirle
        //   a Dropi el histórico completo → OOM + timeout API).
        $horas = (int) $this->option('horas');
        if ($horas < 1 || $horas > 720) {
            $this->error("--horas debe estar entre 1 y 720 (30 días). Recibido: {$horas}");
            return self::FAILURE;
        }

        // Re-audit DR-ι · rate-limit interno: 1 sync máximo por minuto.
        //   Evita disparos concurrentes desde cron mal configurado o admin
        //   spammeando el command a mano.
        if (! $this->option('force')) {
            $key = 'dropi:sync:last';
            $ultimo = Cache::get($key);
            if ($ultimo && (time() - $ultimo) < 60) {
                $segRestantes = 60 - (time() - $ultimo);
                $this->warn("dropi:sync ejecutado hace <60s — espera {$segRestantes}s o usa --force.");
                return self::SUCCESS; // no es error, es idempotencia
            }
            Cache::put($key, time(), 300);
        }

        $desde = CarbonImmutable::now()->subHours($horas);
        $this->info("Sincronizando pedidos Dropi desde {$desde->format('Y-m-d H:i')}...");

        $r = $action->handle($desde);

        $this->table(
            ['Driver', 'Procesados', 'Nuevos', 'Actualizados', 'Pend. inv.', 'Errores'],
            [[$r['driver'], $r['procesados'], $r['nuevos'], $r['actualizados'], $r['pendientes_inv'], $r['errores'] ?? 0]],
        );

        return self::SUCCESS;
    }
}
