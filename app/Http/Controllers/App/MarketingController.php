<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Marketing\Models\MarketingPost;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * M8 · Marketing y Contenido:
 *  - Ficha rich de producto (copy + specs + keywords + beneficios)
 *  - Parrilla mensual: calendario de posts por canal/estado
 */
class MarketingController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware(function (Request $r, \Closure $next) {
            abort_unless($r->user()?->esAracely(), 403);
            return $next($r);
        })];
    }

    // ---------- FICHA RICH PRODUCTO ----------
    public function fichaEditar(int $producto): Response
    {
        $p = Producto::findOrFail($producto);
        return Inertia::render('Marketing/Ficha', [
            'producto' => [
                'id' => $p->id, 'referencia' => $p->referencia, 'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'copy_comercial' => $p->copy_comercial,
                'specs' => is_string($p->specs_json) ? json_decode($p->specs_json, true) : ($p->specs_json ?? []),
                'keywords_seo' => $p->keywords_seo,
                'beneficios' => $p->beneficios,
            ],
        ]);
    }

    public function fichaGuardar(Request $r, int $producto): RedirectResponse
    {
        $data = $r->validate([
            'copy_comercial' => ['nullable', 'string', 'max:3000'],
            'specs' => ['nullable', 'array'],
            'specs.*.k' => ['required_with:specs.*.v', 'string', 'max:60'],
            'specs.*.v' => ['required_with:specs.*.k', 'string', 'max:200'],
            'keywords_seo' => ['nullable', 'string', 'max:500'],
            'beneficios' => ['nullable', 'string', 'max:2000'],
        ]);
        $p = Producto::findOrFail($producto);
        $p->update([
            'copy_comercial' => $data['copy_comercial'] ?? null,
            'specs_json' => json_encode($data['specs'] ?? [], JSON_UNESCAPED_UNICODE),
            'keywords_seo' => $data['keywords_seo'] ?? null,
            'beneficios' => $data['beneficios'] ?? null,
        ]);
        return back()->with('success', 'Ficha marketing actualizada.');
    }

    // ---------- PARRILLA DE CONTENIDO ----------
    public function parrilla(Request $request): Response
    {
        $anio = (int) $request->input('anio', now('America/Bogota')->year);
        $mes = (int) $request->input('mes', now('America/Bogota')->month);
        $inicio = \Carbon\Carbon::create($anio, $mes, 1)->startOfMonth();
        $fin = $inicio->copy()->endOfMonth();

        $posts = MarketingPost::with(['producto:id,nombre,referencia', 'creador:id,name'])
            ->whereBetween('fecha_publicacion', [$inicio->toDateString(), $fin->toDateString()])
            ->orderBy('fecha_publicacion')
            ->get();

        // Agrupar por día para el calendario
        $porDia = [];
        foreach ($posts as $p) {
            $key = $p->fecha_publicacion->format('Y-m-d');
            $porDia[$key][] = [
                'id' => $p->id, 'titulo' => $p->titulo, 'canal' => $p->canal,
                'tipo' => $p->tipo, 'estado' => $p->estado,
                'producto' => $p->producto?->nombre,
            ];
        }

        $conteos = $posts->countBy('estado')->all();

        return Inertia::render('Marketing/Parrilla', [
            'anio' => $anio, 'mes' => $mes,
            'inicio' => $inicio->toDateString(), 'fin' => $fin->toDateString(),
            'diasDelMes' => $fin->day,
            'porDia' => $porDia,
            'total' => $posts->count(),
            'conteos' => $conteos,
            'productos' => Producto::where('activo', true)->orderBy('nombre')->limit(200)->get(['id', 'nombre', 'referencia'])
                ->map(fn ($x) => ['id' => $x->id, 'label' => $x->nombre . ' (' . $x->referencia . ')']),
        ]);
    }

    public function postCrear(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'fecha_publicacion' => ['required', 'date'],
            'hora_publicacion' => ['nullable', 'date_format:H:i'],
            'titulo' => ['required', 'string', 'max:200'],
            'copy' => ['required', 'string', 'max:2000'],
            'canal' => ['required', 'in:instagram,facebook,tiktok,whatsapp_status,email,web,otros'],
            'tipo' => ['required', 'in:post,reel,story,live,email,blog'],
            'estado' => ['nullable', 'in:idea,produccion,programado,publicado,cancelado'],
            'producto_id' => ['nullable', 'integer', 'exists:productos,id'],
            'url_publicacion' => ['nullable', 'url', 'max:500'],
            'notas' => ['nullable', 'string', 'max:1000'],
        ]);
        MarketingPost::create([...$data, 'creado_por' => auth()->id(), 'estado' => $data['estado'] ?? 'idea']);
        return back()->with('success', 'Post agregado a parrilla.');
    }

    public function postActualizar(Request $r, int $post): RedirectResponse
    {
        $data = $r->validate([
            'estado' => ['required', 'in:idea,produccion,programado,publicado,cancelado'],
            'url_publicacion' => ['nullable', 'url', 'max:500'],
        ]);
        MarketingPost::findOrFail($post)->update($data);
        return back()->with('success', 'Post actualizado.');
    }

    public function postEliminar(int $post): RedirectResponse
    {
        MarketingPost::findOrFail($post)->delete();
        return back()->with('success', 'Post eliminado.');
    }
}
