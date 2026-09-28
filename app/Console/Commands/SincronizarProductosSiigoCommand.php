<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Services\SiigoService;
use Illuminate\Console\Command;

/**
 * Sync INCREMENTAL de productos SIIGO → ERP · corre cada 15 min desde
 * routes/console.php. Usa `siigo_config.sync_productos_at` como cursor,
 * y llama /v1/products con `updated_start` para traer solo lo cambiado.
 *
 * Diferencias con `siigo:sync productos`:
 *   - Este es incremental (respeta rate limit 100 req/min, corre frecuente).
 *   - El otro (`SiigoSyncCommand`) sigue existiendo para full sync manual + otros recursos.
 *
 * Flags:
 *   --desde=YYYY-MM-DD  Override manual del cursor (ignora sync_productos_at).
 *   --full              Ignora cursor y trae todos los productos (usar con cuidado).
 */
class SincronizarProductosSiigoCommand extends Command
{
    protected $signature = 'siigo:sync-productos
        {--desde= : Cursor manual yyyy-MM-dd (override del último sync)}
        {--full : Trae todos los productos SIIGO desde el principio}';

    protected $description = 'Sync incremental de productos SIIGO → ERP cada 15 min · respeta rate limit';

    public function handle(): int
    {
        $cfg = SiigoConfig::current();

        if (! $cfg->activo || ! $cfg->username) {
            $this->warn('SIIGO inactivo o sin credenciales · saltando sync.');
            return self::SUCCESS;
        }

        $service = new SiigoService(new SiigoClient($cfg));

        // Determinar el cursor (X1 · usar ISO-8601 UTC con offset · SIIGO acepta
        // updated_start=YYYY-MM-DDTHH:MM:SS+00:00 · antes usábamos Y-m-d puro,
        // lo que (a) recortaba 5h por drift TZ Bogota→UTC y (b) reprocesaba
        // 24h de productos cada 15min · margen ampliado a 5min).
        if ($this->option('full')) {
            $updatedStart = null;
            $this->warn('Modo FULL · ignora cursor y trae todos los productos SIIGO.');
        } elseif ($this->option('desde')) {
            // Si viene solo fecha, la interpretamos como inicio de día UTC.
            $desde = $this->option('desde');
            try {
                $updatedStart = \Illuminate\Support\Carbon::parse($desde)->utc()->format('Y-m-d\TH:i:sP');
            } catch (\Throwable $e) {
                $this->error("--desde inválido: {$desde} · usa YYYY-MM-DD o ISO-8601");
                return self::INVALID;
            }
            $this->info("Cursor manual · pidiendo cambios desde {$updatedStart}");
        } else {
            // Usa el último sync exitoso como cursor (con margen 5min).
            $ultimo = $cfg->sync_productos_at;
            if ($ultimo) {
                $updatedStart = $ultimo->clone()->subMinutes(5)->utc()->format('Y-m-d\TH:i:sP');
                $this->info("Sync incremental · cursor {$updatedStart} (última corrida {$ultimo->format('Y-m-d H:i')})");
            } else {
                // Primera vez → últimos 7 días (para no explotar el rate limit).
                $updatedStart = now()->subDays(7)->utc()->format('Y-m-d\TH:i:sP');
                $this->warn("Primera corrida · trae últimos 7 días desde {$updatedStart}");
            }
        }

        $t0 = microtime(true);
        $resultado = $service->sincronizarProductos(1, 100, $updatedStart);
        $duracion = round(microtime(true) - $t0, 2);

        $this->newLine();
        $this->info("Sync completo en {$duracion}s");
        $this->table(
            ['métrica', 'valor'],
            [
                ['Nuevos', $resultado['nuevos'] ?? 0],
                ['Actualizados', $resultado['actualizados'] ?? 0],
                ['Errores', $resultado['errores'] ?? 0],
                ['Total procesados', $resultado['total'] ?? 0],
            ],
        );

        return ($resultado['errores'] ?? 0) === 0 ? self::SUCCESS : self::FAILURE;
    }
}
