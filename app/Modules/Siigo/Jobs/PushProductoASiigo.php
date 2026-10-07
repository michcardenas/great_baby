<?php

namespace App\Modules\Siigo\Jobs;

use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Actions\ActualizarProductoEnSiigo;
use App\Modules\Siigo\Actions\CrearProductoEnSiigo;
use App\Modules\Siigo\Actions\DesactivarProductoEnSiigo;
use App\Modules\Siigo\Actions\EliminarProductoEnSiigo;
use App\Modules\Siigo\Exceptions\SiigoRateLimitedException;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Push asíncrono de un producto a Siigo API.
 *
 * Responsabilidades:
 *   - Ejecuta la Action correcta (crear/actualizar/desactivar) según el evento.
 *   - Respeta el rate limit de Siigo (config `siigo.rate_limit_per_min`) usando
 *     el middleware RateLimited con el limiter 'siigo-api' registrado en
 *     AppServiceProvider.
 *   - Impide procesamiento concurrente del MISMO producto con
 *     WithoutOverlapping.
 *   - Debounce: `dispatchDebounced()` coalesce por producto (no por acción)
 *     — ver bloque de comentarios en el método.
 *   - Retry backoff: 5 intentos con [10, 30, 60, 120, 300] segundos.
 *   - failed(): registra fallo definitivo en siigo_sync_log.
 *
 * Prerequisitos runtime:
 *   - `CACHE_STORE=database|redis|memcached` (file NO es atómico para Cache::add).
 *   - Worker corriendo con `queue:work database --queue=siigo,default` (esa cola
 *     debe estar en el arranque del worker; sino los jobs quedan varados).
 *   - `DB_QUEUE_RETRY_AFTER >= 120` (default 90 en queue.php · dejamos timeout=60
 *     para quedar por debajo y evitar doble reserva del job por el worker).
 *
 * NO se dispara si `siigo.push_auto = false` (kill-switch global). El caller
 * debe usar `dispatchDebounced()` en vez de `dispatch()` directo.
 */
class PushProductoASiigo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    /**
     * Timeout intencional 60s (< retry_after 90 del queue.php). Si Siigo tarda
     * más, es 429/timeout y el middleware ya maneja el reintento sin re-reservar.
     */
    public int $timeout = 60;

    /**
     * C4 · flag para permitir bypass del kill-switch en `handle()`.
     * `dispatchManual()` lo setea a true · el Observer NUNCA.
     *
     * C3 · `siigoId` opcional se guarda al construir para permitir
     * desactivar en SIIGO aunque el modelo local ya no exista (hard delete
     * entre dispatch y handle).
     */
    public bool $manual = false;
    // El id de SIIGO llega indistintamente como int (de PushAsiento tests y
    //   del Observer en delete→desactivar) o string (algunos payloads de la
    //   UI y de reconciliación). Union type para que PHP no coacciones a
    //   string y los tests comparen con tipo estricto.
    public null|int|string $siigoId = null;

    public function __construct(
        public int $productoId,
        public string $accion,  // 'crear' | 'actualizar' | 'desactivar'
    ) {
        $this->onQueue(config('siigo.queue', 'siigo'));
    }

    /**
     * B4-M4 · backoff con jitter aleatorio (0-20% extra) para evitar
     * thundering herd cuando SIIGO recupera de un 5xx global. Sin jitter,
     * los 100 jobs re-encolados al mismo tiempo caen al mismo slot.
     */
    public function backoff(): array
    {
        return [
            10  + random_int(0, 5),
            30  + random_int(0, 10),
            60  + random_int(0, 15),
            120 + random_int(0, 30),
            300 + random_int(0, 60),
        ];
    }

    /**
     * Middlewares:
     *   - RateLimited('siigo-api') respeta 100 req/min. Libera el job con delay
     *     al próximo slot SIN consumir un `try`.
     *   - WithoutOverlapping impide 2 jobs del mismo producto en simultáneo.
     *     `releaseAfter(60)` alineado con timeout del handle · `dontRelease` NO
     *     porque queremos que el 2do se reencole (con debounce ya viene coalesced).
     */
    public function middleware(): array
    {
        return [
            new RateLimited('siigo-api'),
            (new WithoutOverlapping("siigo:producto:{$this->productoId}"))
                ->releaseAfter(60)
                ->expireAfter(180),
        ];
    }

    /**
     * Fábrica preferida · aplica kill-switch + debounce POR PRODUCTO antes
     * de encolar (bug C2 auditor · antes debounce era por producto+acción,
     * lo cual dejaba pasar avalancha crear→actualizar del mismo producto).
     *
     * Estrategia de coalescing:
     *   - Cache key = `siigo:debounce:{productoId}` (sin acción).
     *   - Value = última acción despachada (`crear` > `actualizar` > `desactivar`
     *     en prioridad si hay múltiples en ventana).
     *   - Si ya existe la key, guarda la nueva acción como pendiente en un array
     *     y no encola de nuevo. El handle() lee la acción efectiva del cache
     *     al momento de ejecutar (ver `handle`).
     *
     * Requiere `CACHE_STORE=database|redis|memcached` para atomicidad.
     */
    public static function dispatchDebounced(int $productoId, string $accion): bool
    {
        if (! \App\Modules\Siigo\Models\SiigoConfig::pushAutoActivo()) {
            return false;
        }

        $key = "siigo:debounce:{$productoId}";
        $ventana = (int) config('siigo.debounce_seconds', 30);

        // Cache::add atómico: si ya existe → false, y solo actualizamos la acción
        // pendiente (que el handle leerá y ejecutará).
        if (! Cache::add($key, $accion, $ventana)) {
            // Ya hay job encolado para este producto · actualizamos la acción
            // "última ganadora" para que cuando corra ejecute la más reciente.
            //   Prioridad: crear > actualizar > desactivar (más "peso").
            $prev = Cache::get($key);
            $ganadora = static::mejorAccion($prev, $accion);
            Cache::put($key, $ganadora, $ventana);
            return false;
        }

        static::dispatch($productoId, $accion);
        return true;
    }

    /**
     * F6 · dispatch MANUAL desde Filament (botón «Sincronizar con SIIGO»).
     *
     * Bypasea el kill-switch `siigo.push_auto` y el debounce — Aracely aprieta
     * el botón porque QUIERE forzar el push AHORA, así lo mande el flag apagado.
     * Igual respeta rate limit (middleware) y `WithoutOverlapping` (no dos
     * jobs del mismo producto al tiempo).
     *
     * C2 · NO reutiliza la key del debounce · pisar `siigo:debounce:{id}`
     * hacía que un Job#1 (Observer) leyera la acción del manual y ejecutara
     * la equivocada, y luego el Job#2 (manual) duplicara el PUT. Ahora la
     * acción viaja como propiedad `$this->accion` en el job propio.
     *
     * NO se usa desde el Observer · exclusivo para acciones humanas explícitas.
     */
    public static function dispatchManual(int $productoId, string $accion, null|int|string $siigoId = null): void
    {
        $job = new static($productoId, $accion);
        $job->manual = true;
        $job->siigoId = $siigoId;
        dispatch($job);
    }

    /**
     * Precedencia de acciones al coalescer.
     * Si en ventana hay `crear` + `actualizar` → gana `crear` (aún no existe siigo_id).
     * Si hay `actualizar` + `desactivar` → gana `desactivar` (usuario decidió borrar).
     * Si hay `crear` + `desactivar` → gana `desactivar`.
     */
    private static function mejorAccion(?string $prev, string $nueva): string
    {
        if (! $prev) return $nueva;
        // desactivar siempre gana (estado final)
        if ($prev === 'desactivar' || $nueva === 'desactivar') return 'desactivar';
        // crear vs actualizar → crear (necesita crearse primero)
        if ($prev === 'crear' || $nueva === 'crear') return 'crear';
        return 'actualizar';
    }

    public function handle(): void
    {
        // C4 · kill-switch en runtime · si Aracely apaga el flag por emergencia
        // (SIIGO 500, presupuesto rate limit, etc.), los jobs YA encolados
        // deben respetarlo. Solo el flag `$manual` (dispatchManual) bypasea.
        // F8 · usa SiigoConfig::pushAutoActivo() que primero mira BD (toggleado
        // desde UI Vue) y solo cae al .env si no hay valor persistido.
        if (! $this->manual && ! \App\Modules\Siigo\Models\SiigoConfig::pushAutoActivo()) {
            SiigoSyncLog::create([
                'recurso' => 'productos',
                'estado' => 'omitido',
                'nuevos' => 0, 'actualizados' => 0, 'errores' => 0,
                'duracion_ms' => 0,
                'mensaje' => "[kill-switch] producto id={$this->productoId} · accion={$this->accion} · push_auto=off",
                'detalle' => ['producto_id' => $this->productoId, 'accion' => $this->accion],
                'user_id' => null,
            ]);
            // Limpiamos la key para que un futuro dispatch no arrastre estado viejo.
            Cache::forget("siigo:debounce:{$this->productoId}");
            return;
        }

        // withTrashed() · un soft-delete deja deleted_at != null; necesitamos
        // la fila para armar el payload completo del PUT que desactiva en SIIGO.
        $producto = Producto::withTrashed()->find($this->productoId);
        if (! $producto) {
            // C3 · si el producto local ya no existe (hard delete entre dispatch
            // y handle) pero traemos siigoId en el job, mandamos igual el
            // desactivar directo a SIIGO — no dejar productos vivos allá.
            if ($this->siigoId && in_array($this->accion, ['desactivar', 'eliminar'], true)) {
                try {
                    $client = app(\App\Modules\Siigo\Clients\SiigoClient::class);
                    // SIIGO rechaza PUT con solo {active:false} · hace falta payload
                    // completo. Hacemos GET primero y reciclamos los campos requeridos.
                    $get = $client->request('GET', "/v1/products/{$this->siigoId}");
                    if ($get->ok()) {
                        $d = $get->json();
                        $payload = [
                            'code' => $d['code'],
                            'name' => $d['name'],
                            'account_group' => $d['account_group']['id'] ?? null,
                            'type' => $d['type'] ?? 'Product',
                            'stock_control' => $d['stock_control'] ?? true,
                            'active' => false,
                            'tax_classification' => $d['tax_classification'] ?? 'Taxed',
                            'tax_included' => $d['tax_included'] ?? false,
                            'taxes' => array_map(fn($t) => ['id' => $t['id']], $d['taxes'] ?? []),
                            'prices' => $d['prices'] ?? [],
                            'unit' => $d['unit']['code'] ?? '94',
                            'unit_label' => $d['unit_label'] ?? 'unidad',
                        ];
                        $client->request('PUT', "/v1/products/{$this->siigoId}", $payload);
                    }
                    SiigoSyncLog::create([
                        'recurso' => 'productos', 'estado' => 'exitoso',
                        'nuevos' => 0, 'actualizados' => 1, 'errores' => 0, 'duracion_ms' => 0,
                        'mensaje' => "[hard-delete-recover] siigo_id={$this->siigoId} · desactivado sin modelo local",
                        'detalle' => ['producto_id' => $this->productoId, 'siigo_id' => $this->siigoId],
                        'user_id' => null,
                    ]);
                } catch (\Throwable $e) {
                    Log::channel('siigo')->error('hard-delete-recover falló', [
                        'siigo_id' => $this->siigoId, 'error' => $e->getMessage(),
                    ]);
                    throw $e;  // que el retry lo agarre
                }
                return;
            }

            // Registrar omisión.
            SiigoSyncLog::create([
                'recurso' => 'productos',
                'estado' => 'omitido',
                'nuevos' => 0, 'actualizados' => 0, 'errores' => 0,
                'duracion_ms' => 0,
                'mensaje' => "[job-omitido] producto id={$this->productoId} · accion={$this->accion} · producto ya no existe",
                'detalle' => ['producto_id' => $this->productoId, 'accion' => $this->accion],
                'user_id' => null,
            ]);
            Log::channel('siigo')->warning('PushProductoASiigo · producto no encontrado', [
                'producto_id' => $this->productoId, 'accion' => $this->accion,
            ]);
            return;
        }

        // Determinación de acción efectiva:
        //   - Manual: usa SIEMPRE la acción del job (C2 · no lee cache).
        //   - Observer: lee cache (coalescing por producto).
        if ($this->manual) {
            $efectiva = $this->accion;
        } else {
            $efectiva = Cache::get("siigo:debounce:{$this->productoId}", $this->accion);
            Cache::forget("siigo:debounce:{$this->productoId}");
        }

        try {
            match ($efectiva) {
                'crear'       => CrearProductoEnSiigo::run($producto),
                'actualizar'  => ActualizarProductoEnSiigo::run($producto),
                'desactivar'  => DesactivarProductoEnSiigo::run($producto),
                'eliminar'    => EliminarProductoEnSiigo::run($producto),
                default       => throw new \InvalidArgumentException("Acción SIIGO inválida: {$efectiva}"),
            };
        } catch (SiigoRateLimitedException $e) {
            // B3-M1 · release al worker SIN gastar un try · con jitter (M4).
            $delay = $e->retryAfter + random_int(0, min(10, (int) ($e->retryAfter * 0.2)));
            $this->release($delay);
        }
    }

    /**
     * Se llama cuando se agotaron los tries. Registra fallo definitivo
     * en siigo_sync_log para que Aracely lo vea desde Filament.
     */
    public function failed(\Throwable $e): void
    {
        SiigoSyncLog::create([
            'recurso' => 'productos',
            'estado' => 'fallido',
            'nuevos' => 0, 'actualizados' => 0, 'errores' => 1,
            'duracion_ms' => 0,
            'mensaje' => "[job-failed] producto={$this->productoId} accion={$this->accion} · {$e->getMessage()}",
            'detalle' => [
                'producto_id' => $this->productoId,
                'accion' => $this->accion,
                'exception' => class_basename($e),
                'trace' => mb_substr($e->getTraceAsString(), 0, 2000),
            ],
            'user_id' => null,
        ]);

        Log::channel('siigo')->error('PushProductoASiigo agotó reintentos', [
            'producto_id' => $this->productoId,
            'accion' => $this->accion,
            'error' => $e->getMessage(),
        ]);

        // FASE F1.C5 · "zombie inverso" · si la acción era `eliminar` o
        // `desactivar` y el producto quedó soft-deleted en el ERP pero NUNCA
        // llegamos a avisar a SIIGO, restauramos el producto para que Aracely
        // pueda intentar manualmente (botón "Forzar re-sync") y no quede
        // divergencia silenciosa (local=borrado, SIIGO=vivo).
        //
        // A2 FIX · envolvemos en withoutEvents() para evitar el loop
        //   restore→ProductoObserver::restored()→nuevo push a SIIGO→falla→
        //   restore→... (reportado en auditoría A2 flujo producto medio #6).
        if (in_array($this->accion, ['eliminar', 'desactivar'], true)) {
            try {
                $p = \App\Modules\Dropi\Models\Producto::onlyTrashed()->find($this->productoId);
                if ($p) {
                    \App\Modules\Dropi\Models\Producto::withoutEvents(fn () => $p->restore());
                    Log::channel('siigo')->warning("Producto {$this->productoId} restaurado tras fallo de SIIGO (withoutEvents · evita loop restored→push)");
                }
            } catch (\Throwable $re) {
                Log::channel('siigo')->error("Failed to auto-restore producto {$this->productoId}: " . $re->getMessage());
            }
        }
    }
}
