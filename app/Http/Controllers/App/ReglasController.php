<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Support\Reglas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class ReglasController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless($r->user()?->esAracely(), 403, 'Solo administración puede editar reglas.');
                return $next($r);
            }),
        ];
    }

    public function index(): Response
    {
        // Sembrar cualquier regla nueva del código que aún no esté en BD.
        Reglas::seedDefaults();

        return Inertia::render('Reglas', [
            'grupos' => Reglas::porGrupo(),
            'gruposLabels' => [
                'empaque' => '📦 Estación de Empaque',
                'dashboard' => '📊 Torre de Control',
                'cartera' => '💰 Cartera',
                'dropi' => '🚚 Dropi',
                'catalogo' => '🏷️ Catálogo',
                'crm' => '👥 CRM',
                'contable' => '📚 Contabilidad',
            ],
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'reglas' => ['required', 'array'],
            'reglas.*.clave' => ['required', 'string'],
            'reglas.*.valor' => ['nullable'],
        ]);

        $actualizadas = 0;
        $errores = [];
        foreach ($data['reglas'] as $r) {
            try {
                Reglas::set($r['clave'], $r['valor'] ?? '');
                $actualizadas++;
            } catch (\InvalidArgumentException $e) {
                $errores[] = $r['clave'] . ': ' . $e->getMessage();
            }
        }

        $flash = ['success' => "✅ {$actualizadas} reglas actualizadas"];
        if (! empty($errores)) {
            $flash['warning'] = "⚠ No se pudieron guardar: " . implode(' · ', $errores);
        }
        return back()->with($flash);
    }
}
