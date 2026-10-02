<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use App\Modules\Siigo\Jobs\ReconciliarProductosDesdeSiigo;
use Illuminate\Support\Facades\Cache;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * F8 · Panel Vue "CONFIGURACIÓN → SIIGO" en /app/siigo.
 * Reutiliza SiigoConfig, SiigoSyncLog y PushProductoASiigo existentes · sin
 * crear nuevos modelos ni tablas. Todo el estado (kill-switch, KPIs, cola,
 * fallidos) sale de las tablas siigo_sync_log + siigo_config + jobs.
 */
class SiigoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                // F8 · ampliado: antes solo Aracely · ahora Aracely + Gerente
                // (Gerente puede monitorear pero solo Aracely toggleaKillSwitch).
                $u = $r->user();
                abort_unless(
                    $u?->esAracely() || $u?->hasAnyRole(['Gerente', 'Gerencia']),
                    403
                );
                return $next($r);
            }),
        ];
    }

    public function index(): Response
    {
        $cfg = SiigoConfig::current();
        $ultimosLogs = SiigoSyncLog::query()->orderByDesc('id')->limit(20)->get();

        return Inertia::render('Siigo/Index', [
            'config' => [
                'id' => $cfg->id,
                'ambiente' => $cfg->ambiente,
                'activo' => (bool) $cfg->activo,
                'usuario' => $cfg->username,
                'partner_id' => $cfg->partner_id,
                'push_auto' => SiigoConfig::pushAutoActivo(),
                'push_auto_source' => $cfg->push_auto !== null ? 'ui' : 'env',
                'push_auto_updated_at' => $cfg->push_auto_updated_at?->diffForHumans(),
                'sync_productos_at' => $cfg->sync_productos_at?->diffForHumans(),
                'sync_clientes_at' => $cfg->sync_clientes_at?->diffForHumans(),
                'sync_catalogos_at' => $cfg->sync_catalogos_at?->diffForHumans(),
            ],
            'kpis' => $this->kpis(),
            'cola' => $this->cola(),
            'fallidos_recientes' => $this->fallidosRecientes(),
            'logs' => $ultimosLogs->map(fn ($l) => $this->serializarLog($l))->all(),
            'puede_toggle' => (bool) auth()->user()?->esAracely(),
            'reconciliar' => $this->estadoReconciliarVista(),
        ]);
    }

    /**
     * F8 · toggle del kill-switch push_auto. Solo Aracely.
     * Persiste en siigo_config.push_auto y limpia cache.
     */
    public function toggleKillSwitch(Request $r): RedirectResponse
    {
        abort_unless($r->user()?->esAracely(), 403);

        $r->validate(['activo' => 'required|boolean']);

        $cfg = SiigoConfig::current();
        $cfg->forceFill([
            'push_auto' => (bool) $r->boolean('activo'),
            'push_auto_updated_at' => now(),
            'push_auto_updated_by' => $r->user()->id,
        ])->save();

        SiigoConfig::invalidarPushAutoCache();

        return back()->with('flash', [
            'type' => 'success',
            'message' => $cfg->push_auto ? 'Sync automático ENCENDIDO ✅' : 'Sync automático APAGADO ⏸',
        ]);
    }

    /**
     * F8 · reintentar un job fallido. Toma el producto_id del log.detalle y
     * dispara dispatchManual (bypasea kill-switch y debounce).
     */
    public function reintentar(SiigoSyncLog $log, Request $r): RedirectResponse
    {
        $productoId = (int) ($log->detalle['producto_id'] ?? 0);
        $accion = (string) ($log->detalle['accion'] ?? 'actualizar');

        if (! $productoId) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Log sin producto_id · no se puede reintentar.',
            ]);
        }

        // Recuperar siigoId por si el producto ya no existe (hard delete recovery).
        $siigoId = Producto::withTrashed()->where('id', $productoId)->value('siigo_id');

        PushProductoASiigo::dispatchManual($productoId, $accion, $siigoId);

        return back()->with('flash', [
            'type' => 'success',
            'message' => "Sync manual encolado · producto {$productoId} · {$accion}",
        ]);
    }

    /**
     * Sincronizar catálogos de SIIGO al ERP (taxes, account-groups, warehouses,
     * price-lists, document-types, payment-types) + propagar siigo_id a las
     * tablas locales (impuestos, categorias, listas_precios).
     *
     * Esto es la base para que los selectores del form de productos tengan
     * opciones reales de SIIGO en vez de datos inventados localmente.
     */
    public function sincronizarCatalogos(): JsonResponse
    {
        try {
            $svc = new \App\Modules\Siigo\Services\SiigoService(
                new \App\Modules\Siigo\Clients\SiigoClient(SiigoConfig::current())
            );
            $r = $svc->sincronizarCatalogos();
            return response()->json(['ok' => true, 'resumen' => $r]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'mensaje' => $e->getMessage()], 500);
        }
    }

    /**
     * Importar UN producto puntual desde SIIGO al ERP por su `code`.
     * Útil para sandbox compartido (evita traer basura del tenant) y
     * para cuando el usuario quiere traer un producto específico.
     * Si ya existe en el ERP (match por siigo_id o referencia=code), lo actualiza.
     */
    public function importarPorCode(Request $r): JsonResponse
    {
        $code = trim((string) $r->input('code'));
        if ($code === '') {
            return response()->json(['ok' => false, 'mensaje' => 'Falta el code'], 422);
        }
        $client = new \App\Modules\Siigo\Clients\SiigoClient(SiigoConfig::current());
        $resp = $client->request('GET', '/v1/products', ['code' => $code]);
        if ($resp->failed()) {
            return response()->json(['ok' => false, 'mensaje' => 'SIIGO HTTP '.$resp->status()], 502);
        }
        $items = $resp->json('results') ?? [];
        if (empty($items)) {
            return response()->json(['ok' => false, 'mensaje' => "SIIGO no tiene ningún producto con code={$code}"]);
        }
        $svc = new \App\Modules\Siigo\Services\SiigoService($client);
        $metodo = (new \ReflectionClass($svc))->getMethod('guardarProducto');
        $metodo->setAccessible(true);
        $nuevos = 0; $actualizados = 0;
        foreach ($items as $p) {
            $existente = \App\Modules\Dropi\Models\Producto::where('siigo_id', $p['id'])
                ->orWhere('referencia', $p['code'] ?? null)->first();
            $creado = $metodo->invoke($svc, $p, $existente);
            $creado ? $nuevos++ : $actualizados++;
        }
        return response()->json([
            'ok' => true,
            'resumen' => compact('nuevos', 'actualizados'),
            'mensaje' => "+{$nuevos} nuevos · {$actualizados} actualizados · total {$this->totalErp()}",
        ]);
    }

    private function totalErp(): int
    {
        return \App\Modules\Dropi\Models\Producto::count();
    }

    /**
     * Reconciliación bajo demanda · dispara el pull completo SIIGO→ERP
     * con detección de zombies. Devuelve resumen para toast en el panel.
     */
    /**
     * B2/B3 · Despacha reconciliación INCREMENTAL (modo seguro) a la cola.
     *
     * Trae solo lo que cambió en SIIGO desde siigo_config.sync_productos_at
     * (típicamente <30 seg). El pull completo con detección de zombies
     * NO está expuesto en la UI · se corre con
     *     php artisan siigo:reconciliar --full --confirmar
     * para evitar accidentes como traer 20k productos del sandbox compartido.
     */
    public function reconciliar(): JsonResponse
    {
        $estado = Cache::get(ReconciliarProductosDesdeSiigo::CACHE_KEY);
        if (($estado['estado'] ?? null) === 'corriendo') {
            return response()->json([
                'ok' => true,
                'yaCorriendo' => true,
                'mensaje' => 'Ya hay una sincronización en curso · espera a que termine.',
                'inicio' => $estado['inicio'] ?? null,
            ]);
        }

        ReconciliarProductosDesdeSiigo::dispatch(full: false)->onQueue('siigo');

        $desde = SiigoConfig::current()->sync_productos_at;
        Cache::put(ReconciliarProductosDesdeSiigo::CACHE_KEY, [
            'estado' => 'corriendo',
            'modo' => 'incremental',
            'inicio' => now()->toIso8601String(),
            'desde' => $desde?->toIso8601String(),
            'resumen' => null,
            'error' => null,
        ], 3600);

        return response()->json([
            'ok' => true,
            'encolado' => true,
            'modo' => 'incremental',
            'desde' => $desde?->toIso8601String(),
            'mensaje' => $desde
                ? "Trayendo cambios desde {$desde->diffForHumans()}…"
                : 'Primera sincronización · trayendo últimos 7 días…',
        ]);
    }

    /** Polling · consulta estado de la última reconciliación. */
    /**
     * FASE E · Semáforo ligero · consulta rápida para el header global.
     * Retorna {color, label, pendientes, errores24h, push_auto}.
     */
    public function semaforo(): JsonResponse
    {
        $pendientes = 0;
        try {
            $pendientes = (int) \DB::table('jobs')
                ->where('queue', config('siigo.queue', 'siigo'))
                ->count();
        } catch (\Throwable) {}

        $errores24h = 0;
        try {
            $errores24h = (int) \App\Modules\Siigo\Models\SiigoSyncLog::query()
                ->where('estado', 'fallido')
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Throwable) {}

        $pushAuto = SiigoConfig::pushAutoActivo();

        [$color, $label] = match (true) {
            ! $pushAuto             => ['amber', 'Push automático pausado'],
            $errores24h > 0          => ['red',   "{$errores24h} errores 24h"],
            $pendientes > 20         => ['amber', "{$pendientes} en cola"],
            default                  => ['emerald','Al día'],
        };

        return response()->json([
            'color' => $color,
            'label' => $label,
            'pendientes' => $pendientes,
            'errores24h' => $errores24h,
            'push_auto' => $pushAuto,
        ]);
    }

    public function reconciliarEstado(): JsonResponse
    {
        $e = Cache::get(ReconciliarProductosDesdeSiigo::CACHE_KEY, [
            'estado' => 'idle',
            'resumen' => null,
            'error' => null,
        ]);
        return response()->json($e);
    }

    /**
     * A2 · Deshacer la última reconciliación · soft-delete de todos los
     * productos SIIGO traídos en la ventana (inicio..fin) de la última
     * corrida. Protege productos con movimientos, variantes o editados
     * manualmente. Reutiliza el comando siigo:limpiar-sandbox.
     */
    public function deshacerUltimaReconciliacion(Request $req): JsonResponse
    {
        abort_unless($req->user()?->esAracely(), 403, 'Solo Aracely puede deshacer sincs.');

        $estado = Cache::get(ReconciliarProductosDesdeSiigo::CACHE_KEY);
        if (! $estado || ($estado['estado'] ?? null) !== 'completado'
            || empty($estado['inicio']) || empty($estado['fin'])) {
            return response()->json([
                'ok' => false,
                'mensaje' => 'No hay reconciliación completada en el cache para deshacer.',
            ], 400);
        }

        $confirmar = (bool) $req->boolean('confirmar');

        $exit = \Artisan::call('siigo:limpiar-sandbox', [
            '--desde' => $estado['inicio'],
            '--hasta' => $estado['fin'],
            '--confirmar' => $confirmar,
        ]);
        $salida = \Artisan::output();

        return response()->json([
            'ok' => $exit === 0,
            'confirmado' => $confirmar,
            'ventana' => ['desde' => $estado['inicio'], 'fin' => $estado['fin']],
            'salida' => $salida,
        ]);
    }

    /**
     * Visor en vivo · hace GET /v1/products/{uuid} contra SIIGO sandbox para que
     * el usuario vea en el mismo preview lo que SIIGO tiene (verificación
     * bidireccional del CRUD, sin salir del ERP ni usar Postman).
     * Si el producto es granular, trae la 1ra variante que tenga siigo_id.
     */
    public function verificarProducto(Producto $producto): JsonResponse
    {
        $producto->loadMissing('variantes');
        $siigoId = $producto->siigo_id;
        $siigoCode = $producto->siigo_code;
        $origen = 'producto';
        if (! $siigoId) {
            $v = $producto->variantes->firstWhere(fn ($x) => (bool) $x->siigo_id);
            if ($v) { $siigoId = $v->siigo_id; $siigoCode = $v->siigo_code; $origen = "variante #{$v->id}"; }
        }
        if (! $siigoId) {
            return response()->json([
                'ok' => false,
                'motivo' => 'Este producto no tiene siigo_id · nunca se sincronizó',
                'producto' => ['id' => $producto->id, 'referencia' => $producto->referencia, 'nombre' => $producto->nombre],
            ]);
        }
        $client = new \App\Modules\Siigo\Clients\SiigoClient(SiigoConfig::current());
        $r = $client->request('GET', "/v1/products/{$siigoId}");
        $en_variantes = $producto->variantes->map(fn ($v) => [
            'id' => $v->id, 'siigo_id' => $v->siigo_id, 'siigo_code' => $v->siigo_code,
            'color' => $v->color_nombre, 'talla' => $v->talla, 'sync_at' => $v->siigo_sync_at,
        ])->values();
        return response()->json([
            'ok' => $r->ok(),
            'http' => $r->status(),
            'origen_consulta' => $origen,
            'siigo_id_consultado' => $siigoId,
            'siigo_code' => $siigoCode,
            'respuesta_siigo' => $r->json(),
            'producto_local' => [
                'id' => $producto->id,
                'referencia' => $producto->referencia,
                'nombre' => $producto->nombre,
                'siigo_id' => $producto->siigo_id,
                'siigo_sync_at' => $producto->siigo_sync_at,
                'variantes' => $en_variantes,
            ],
        ]);
    }

    /** F8 · bitácora extendida con filtros + paginación. */
    public function logs(Request $r): JsonResponse
    {
        $q = SiigoSyncLog::query()->orderByDesc('id');

        if ($estado = $r->string('estado')->toString()) $q->where('estado', $estado);
        if ($recurso = $r->string('recurso')->toString()) $q->where('recurso', $recurso);
        if ($desde = $r->string('desde')->toString()) $q->whereDate('created_at', '>=', $desde);
        if ($hasta = $r->string('hasta')->toString()) $q->whereDate('created_at', '<=', $hasta);

        $rows = $q->paginate(min(100, (int) $r->integer('per_page', 30)));

        return response()->json([
            'data' => collect($rows->items())->map(fn ($l) => $this->serializarLog($l))->all(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    /** KPIs del día (queries directas · sin cache · corren en <100 ms). */
    private function kpis(): array
    {
        $desdeHoy = now()->startOfDay();
        $desdeSemana = now()->subDays(7);

        return [
            'productos_hoy_exitosos' => SiigoSyncLog::where('recurso', 'productos')
                ->where('estado', 'exitoso')
                ->where('created_at', '>=', $desdeHoy)
                ->sum(DB::raw('nuevos + actualizados')),
            'productos_hoy_fallidos' => SiigoSyncLog::where('recurso', 'productos')
                ->where('estado', 'fallido')
                ->where('created_at', '>=', $desdeHoy)
                ->count(),
            'productos_sin_siigo_id' => Producto::whereNull('siigo_id')->where('activo', true)->count(),
            'total_semana' => SiigoSyncLog::where('created_at', '>=', $desdeSemana)->count(),
        ];
    }

    /** Snapshot de la cola siigo · lee tabla `jobs` directo. */
    private function cola(): array
    {
        try {
            $filas = DB::table('jobs')
                ->where('queue', config('siigo.queue', 'siigo'))
                ->orderBy('id')
                ->limit(10)
                ->get(['id', 'payload', 'attempts', 'available_at']);
        } catch (\Throwable) {
            return ['pendientes' => 0, 'proximos' => []];
        }

        return [
            'pendientes' => (int) DB::table('jobs')
                ->where('queue', config('siigo.queue', 'siigo'))
                ->count(),
            'proximos' => $filas->map(function ($f) {
                $payload = json_decode($f->payload, true);
                $data = $payload['data']['command'] ?? '';
                // extrae producto id de la firma del job (unserialize sería costoso · regex simple)
                preg_match('/"productoId";i:(\d+)/', $data, $m);
                preg_match('/"accion";s:\d+:"(\w+)"/', $data, $ma);
                return [
                    'id' => $f->id,
                    'producto_id' => (int) ($m[1] ?? 0),
                    'accion' => $ma[1] ?? '?',
                    'attempts' => (int) $f->attempts,
                    'disponible_en' => now()->createFromTimestamp((int) $f->available_at)->diffForHumans(),
                ];
            })->all(),
        ];
    }

    /** Últimos fallidos con datos para el botón reintentar. */
    /**
     * B5 · Snapshot del estado de la última reconciliación para la vista
     * principal de /app/siigo. Lee el cache CACHE_KEY (dashboard de arriba
     * no necesita ser live · basta con mostrar último estado).
     */
    private function estadoReconciliarVista(): array
    {
        $estado = Cache::get(ReconciliarProductosDesdeSiigo::CACHE_KEY) ?: ['estado' => 'idle'];
        return [
            'estado' => $estado['estado'] ?? 'idle',
            'modo' => $estado['modo'] ?? null,
            'inicio' => $estado['inicio'] ?? null,
            'fin' => $estado['fin'] ?? null,
            'resumen' => $estado['resumen'] ?? null,
            'error' => $estado['error'] ?? null,
            'hace' => isset($estado['fin']) ? \Carbon\Carbon::parse($estado['fin'])->diffForHumans() : null,
        ];
    }

    private function fallidosRecientes(): array
    {
        return SiigoSyncLog::where('estado', 'fallido')
            ->orderByDesc('id')
            ->limit(15)
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'producto_id' => (int) ($l->detalle['producto_id'] ?? 0),
                'accion' => $l->detalle['accion'] ?? '—',
                'http_status' => (int) ($l->detalle['http_status'] ?? 0),
                'mensaje' => $l->mensaje,
                'hace' => $l->created_at?->diffForHumans(),
            ])
            ->all();
    }

    private function serializarLog(SiigoSyncLog $l): array
    {
        return [
            'id' => $l->id,
            'recurso' => $l->recurso,
            'estado' => $l->estado,
            'nuevos' => (int) $l->nuevos,
            'actualizados' => (int) $l->actualizados,
            'errores' => (int) $l->errores,
            'duracion_ms' => (int) $l->duracion_ms,
            'mensaje' => $l->mensaje,
            'hace' => $l->created_at?->diffForHumans(),
            'created_at' => $l->created_at?->toIso8601String(),
            'detalle' => $l->detalle,
        ];
    }
}
