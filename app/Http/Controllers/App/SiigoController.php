<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Siigo\Models\SiigoConfig;
use App\Modules\Siigo\Models\SiigoSyncLog;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class SiigoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403);
                return $next($r);
            }),
        ];
    }

    public function index(): Response
    {
        $cfg = SiigoConfig::query()->first();
        $ultimosLogs = SiigoSyncLog::query()->orderByDesc('id')->limit(20)->get();

        return Inertia::render('Siigo/Index', [
            'config' => $cfg ? $cfg->only(['id','ambiente','usuario','conectado_at','contadores']) : null,
            'logs' => $ultimosLogs->map(fn ($l) => [
                'id' => $l->id,
                'tipo' => $l->tipo ?? '—',
                'exitoso' => (bool) ($l->exitoso ?? false),
                'items_procesados' => (int) ($l->items_procesados ?? 0),
                'items_error' => (int) ($l->items_error ?? 0),
                'mensaje' => $l->mensaje,
                'hace' => $l->created_at?->diffForHumans(),
                'created_at' => $l->created_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
