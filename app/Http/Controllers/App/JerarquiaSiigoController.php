<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\CatalogoClase;
use App\Modules\Catalogo\Models\CatalogoGrupo;
use App\Modules\Catalogo\Models\CatalogoLinea;
use App\Modules\Catalogo\Models\CatalogoSubgrupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sprint 4 · G.2 · CRUD de la jerarquía SIIGO (Línea/Grupo/Subgrupo/Clase).
 * Ruta: /app/catalogo/jerarquia-siigo
 */
class JerarquiaSiigoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function (Request $r, \Closure $next) {
                abort_unless(\App\Auth\Permisos::puede($r->user(), 'productos'), 403);
                return $next($r);
            }),
        ];
    }

    public function index(): Response
    {
        return Inertia::render('Catalogo/JerarquiaSiigo', [
            'lineas' => CatalogoLinea::orderBy('codigo')->get(['id', 'codigo', 'nombre', 'activa', 'siigo_id']),
            'grupos' => CatalogoGrupo::with('linea:id,codigo,nombre')->orderBy('linea_id')->orderBy('codigo')
                ->get(['id', 'linea_id', 'codigo', 'nombre', 'activa', 'siigo_id']),
            'subgrupos' => CatalogoSubgrupo::with('grupo.linea')->orderBy('grupo_id')->orderBy('codigo')
                ->get(['id', 'grupo_id', 'codigo', 'nombre', 'activa'])
                ->map(fn ($s) => [
                    'id' => $s->id, 'grupo_id' => $s->grupo_id,
                    'codigo' => $s->codigo, 'nombre' => $s->nombre, 'activa' => $s->activa,
                    'grupo_label' => $s->grupo ? ($s->grupo->linea?->codigo . ' / ' . $s->grupo->codigo) : '—',
                ]),
            'clases' => CatalogoClase::with('subgrupo.grupo.linea')->orderBy('subgrupo_id')->orderBy('codigo')
                ->get(['id', 'subgrupo_id', 'codigo', 'nombre', 'activa'])
                ->map(fn ($c) => [
                    'id' => $c->id, 'subgrupo_id' => $c->subgrupo_id,
                    'codigo' => $c->codigo, 'nombre' => $c->nombre, 'activa' => $c->activa,
                    'subgrupo_label' => $c->subgrupo?->grupo
                        ? ($c->subgrupo->grupo->linea?->codigo . ' / ' . $c->subgrupo->grupo->codigo . ' / ' . $c->subgrupo->codigo)
                        : '—',
                ]),
        ]);
    }

    public function lineaGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
            'siigo_id' => ['nullable', 'string', 'max:60'],
        ]);
        $data['activa'] ??= true;
        if (! empty($data['id'])) {
            CatalogoLinea::where('id', $data['id'])->update($data);
        } else {
            CatalogoLinea::create($data);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Línea guardada.']);
    }

    public function lineaEliminar(CatalogoLinea $linea): RedirectResponse
    {
        if ($linea->grupos()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No se puede eliminar · tiene grupos asociados.']);
        }
        $linea->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Línea eliminada.']);
    }

    public function grupoGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer'],
            'linea_id' => ['required', 'integer', 'exists:catalogo_lineas,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
            'siigo_id' => ['nullable', 'string', 'max:60'],
        ]);
        $data['activa'] ??= true;
        if (! empty($data['id'])) {
            CatalogoGrupo::where('id', $data['id'])->update($data);
        } else {
            CatalogoGrupo::create($data);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Grupo guardado.']);
    }

    public function grupoEliminar(CatalogoGrupo $grupo): RedirectResponse
    {
        if ($grupo->subgrupos()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No se puede eliminar · tiene subgrupos asociados.']);
        }
        $grupo->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Grupo eliminado.']);
    }

    public function subgrupoGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer'],
            'grupo_id' => ['required', 'integer', 'exists:catalogo_grupos,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
        ]);
        $data['activa'] ??= true;
        if (! empty($data['id'])) {
            CatalogoSubgrupo::where('id', $data['id'])->update($data);
        } else {
            CatalogoSubgrupo::create($data);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Subgrupo guardado.']);
    }

    public function subgrupoEliminar(CatalogoSubgrupo $subgrupo): RedirectResponse
    {
        if ($subgrupo->clases()->exists()) {
            return back()->with('flash', ['type' => 'error', 'message' => 'No se puede eliminar · tiene clases asociadas.']);
        }
        $subgrupo->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Subgrupo eliminado.']);
    }

    public function claseGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer'],
            'subgrupo_id' => ['required', 'integer', 'exists:catalogo_subgrupos,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
        ]);
        $data['activa'] ??= true;
        if (! empty($data['id'])) {
            CatalogoClase::where('id', $data['id'])->update($data);
        } else {
            CatalogoClase::create($data);
        }
        return back()->with('flash', ['type' => 'success', 'message' => 'Clase guardada.']);
    }

    public function claseEliminar(CatalogoClase $clase): RedirectResponse
    {
        $clase->delete();
        return back()->with('flash', ['type' => 'success', 'message' => 'Clase eliminada.']);
    }
}
