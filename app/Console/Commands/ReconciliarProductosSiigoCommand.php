<?php

namespace App\Console\Commands;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Jobs\ReconciliarProductosDesdeSiigo;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Services\SiigoService;
use Illuminate\Console\Command;

/**
 * B3 · Reconciliar productos SIIGO → ERP desde la línea de comandos.
 *
 * Dos modos:
 *   # Incremental (default · seguro · rápido)
 *   php artisan siigo:reconciliar
 *   php artisan siigo:reconciliar --desde=2026-10-01
 *
 *   # Full con detección de zombies (destructivo · pide --confirmar)
 *   php artisan siigo:reconciliar --full --confirmar
 *
 * El modo FULL se expone solo acá porque:
 *   - Trae TODOS los productos del tenant SIIGO (en sandbox compartido eso
 *     significa miles de productos ajenos)
 *   - Marca como inactivos los que SIIGO ya no tiene (puede inflar la papelera)
 *
 * Para dispararlo en background sin bloquear, pasa --async y encola el Job.
 */
class ReconciliarProductosSiigoCommand extends Command
{
    protected $signature = 'siigo:reconciliar
                            {--full : Pull completo con detección de zombies (requiere --confirmar)}
                            {--desde= : updated_start YYYY-MM-DD (solo en modo incremental)}
                            {--confirmar : Confirma modo full}
                            {--async : Encola Job en vez de correr síncrono}';

    protected $description = 'Reconciliar productos SIIGO → ERP · incremental por default';

    public function handle(): int
    {
        $full = (bool) $this->option('full');
        $async = (bool) $this->option('async');

        if ($full && ! $this->option('confirmar')) {
            $this->error('Modo --full requiere --confirmar. Puede traer miles de productos del sandbox compartido.');
            $this->line('Alternativa: corre sin --full para traer solo cambios recientes.');
            return self::FAILURE;
        }

        if ($async) {
            ReconciliarProductosDesdeSiigo::dispatch(
                full: $full,
                updatedStart: $this->option('desde'),
            )->onQueue('siigo');
            $this->info('Job encolado en la cola "siigo". Estado: Cache::get(\''
                . ReconciliarProductosDesdeSiigo::CACHE_KEY . '\')');
            return self::SUCCESS;
        }

        $svc = new SiigoService(new SiigoClient(SiigoConfig::current()));
        $t0 = microtime(true);

        if ($full) {
            $this->warn('Modo FULL · esto puede tardar varios minutos y traer miles de productos.');
            $r = $svc->reconciliarProductos();
        } else {
            $cursor = $this->option('desde')
                ?? SiigoConfig::current()->sync_productos_at?->toDateString()
                ?? now()->subDays(7)->toDateString();
            $this->info("Modo incremental · updated_start = {$cursor}");
            $r = $svc->sincronizarProductos(1, 100, $cursor);
        }

        $ms = (int) round((microtime(true) - $t0) * 1000);
        $this->newLine();
        $this->table(['Métrica', 'Valor'], array_map(
            fn ($k, $v) => [$k, is_array($v) ? json_encode($v) : (string) $v],
            array_keys($r), array_values($r)
        ));
        $this->info("Terminado en {$ms}ms.");
        return self::SUCCESS;
    }
}
