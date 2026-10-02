<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Siigo\Clients\SiigoClient;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Services\SiigoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Reconciliar productos SIIGO → ERP de forma asíncrona.
 *
 * Modos:
 *   - INCREMENTAL (default) · usa SiigoService::sincronizarProductos con
 *     updated_start = siigo_config.sync_productos_at (o el pasado al Job).
 *     En uso normal, cada click en "Traer mis cambios de SIIGO" solo trae
 *     lo que cambió desde la última sync → típicamente <30 seg.
 *
 *   - FULL · usa SiigoService::reconciliarProductos (pull completo con
 *     detección de zombies). Solo se dispara desde CLI con confirmación
 *     explícita · nunca desde la UI, para evitar los 14 minutos y los 20k
 *     productos basura del sandbox compartido.
 */
class ReconciliarProductosDesdeSiigo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public const CACHE_KEY = 'siigo:reconciliar:estado';

    public function __construct(
        public bool $full = false,
        public ?string $updatedStart = null,
    ) {}

    public function handle(): void
    {
        $modo = $this->full ? 'full' : 'incremental';

        Cache::put(self::CACHE_KEY, [
            'estado' => 'corriendo',
            'modo' => $modo,
            'inicio' => now()->toIso8601String(),
            'resumen' => null,
            'error' => null,
        ], 3600);

        try {
            $svc = new SiigoService(new SiigoClient(SiigoConfig::current()));

            if ($this->full) {
                $resumen = $svc->reconciliarProductos();
            } else {
                // INCREMENTAL · usa updated_start explícito, o el cursor
                // guardado en siigo_config, o 7 días atrás si nunca sincronizó.
                $cursor = $this->updatedStart
                    ?? SiigoConfig::current()->sync_productos_at?->toDateString()
                    ?? now()->subDays(7)->toDateString();

                $r = $svc->sincronizarProductos(1, 100, $cursor);
                // Normalizamos la forma del resumen para que el UI consuma lo mismo
                // que recibe del modo full.
                $resumen = [
                    'nuevos' => $r['nuevos'] ?? 0,
                    'actualizados' => $r['actualizados'] ?? 0,
                    'linkeados' => 0,
                    'zombies' => 0,
                    'errores' => $r['errores'] ?? 0,
                    'total' => $r['total'] ?? 0,
                    'desde' => $cursor,
                ];
            }

            Cache::put(self::CACHE_KEY, [
                'estado' => 'completado',
                'modo' => $modo,
                'inicio' => Cache::get(self::CACHE_KEY)['inicio'] ?? null,
                'fin' => now()->toIso8601String(),
                'resumen' => $resumen,
                'error' => null,
            ], 3600);
        } catch (\Throwable $e) {
            Log::channel('siigo')->error('ReconciliarProductosDesdeSiigo falló', [
                'modo' => $modo,
                'err' => $e->getMessage(),
            ]);
            Cache::put(self::CACHE_KEY, [
                'estado' => 'fallido',
                'modo' => $modo,
                'fin' => now()->toIso8601String(),
                'resumen' => null,
                'error' => $e->getMessage(),
            ], 3600);
        }
    }

    // FASE F4 · failed() del Job pisa el estado a 'fallido' si crashea antes
    // de llegar al catch (OOM, timeout del worker). Sin esto el cache quedaba
    // 'corriendo' 1h y el botón de la UI no permitía relanzar.
    public function failed(\Throwable $e): void
    {
        Cache::put(self::CACHE_KEY, [
            'estado' => 'fallido',
            'fin' => now()->toIso8601String(),
            'resumen' => null,
            'error' => 'Job crasheó: ' . $e->getMessage(),
        ], 3600);
    }
}
