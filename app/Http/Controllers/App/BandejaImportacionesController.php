<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImportacionBandeja;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class BandejaImportacionesController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esAracely(), 403);
            return $next($r);
        })];
    }

    public function index(): Response
    {
        $lote = ImportacionBandeja::with('user:id,name')->orderByDesc('created_at')->limit(50)->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'tipo' => $i->tipo ?? '—',
                'archivo' => $i->archivo_nombre ?? '—',
                'estado' => $i->estado ?? '—',
                'total_filas' => (int) ($i->total_filas ?? 0),
                'procesadas' => (int) ($i->procesadas ?? 0),
                'exitosas' => (int) ($i->exitosas ?? 0),
                'fallidas' => (int) ($i->fallidas ?? 0),
                'porcentaje' => $i->total_filas > 0 ? (int) round(($i->procesadas / $i->total_filas) * 100) : 0,
                'usuario' => $i->user?->name,
                'iniciada' => $i->iniciada_at?->format('Y-m-d H:i'),
                'terminada' => $i->terminada_at?->format('Y-m-d H:i'),
                'log' => is_array($i->log) ? array_slice($i->log, 0, 20) : [],
            ]);
        return Inertia::render('Bandeja/Index', ['lotes' => $lote]);
    }
}
