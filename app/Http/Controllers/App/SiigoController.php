<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
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
