<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\PrecioVariante;
use App\Modules\Dropi\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class PortalCatalogoController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('auth:cliente')];
    }

    public function index(Request $request): Response
    {
        $cliente = $request->user('cliente');
        $listaId = (int) ($cliente->lista_precios_id ?? 0);
        $q = trim((string) $request->input('q', ''));
        $marca = (int) $request->input('marca_id', 0);

        $productos = Producto::query()
            ->where('activo', true)
            ->when($q, fn ($qb) => $qb->where(function ($w) use ($q) {
                $w->where('referencia', 'like', "%{$q}%")
                    ->orWhere('nombre', 'like', "%{$q}%");
            }))
            ->when($marca, fn ($qb) => $qb->where('marca_id', $marca))
            ->with(['variantes:id,producto_id,color_nombre,talla,codigo_barras'])
            ->orderBy('referencia')
            ->paginate(24)
            ->withQueryString();

        // Precios por variante para las variantes visibles en esta página.
        $varianteIds = $productos->getCollection()->pluck('variantes.*.id')->flatten()->all();
        $precios = $listaId
            ? PrecioVariante::whereIn('variante_id', $varianteIds)
                ->where('lista_id', $listaId)
                ->where(function ($w) {
                    $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
                })
                ->pluck('precio', 'variante_id')
                ->all()
            : [];

        $data = $productos->getCollection()->map(function ($p) use ($precios) {
            $preciosVar = collect($p->variantes)
                ->map(fn ($v) => (float) ($precios[$v->id] ?? 0))
                ->filter(fn ($x) => $x > 0);
            // Fix demo D2 · imagen del producto (media library o fallback
            //   a placeholder ilustrado coloreado por referencia — evita el
            //   catálogo con placeholders gris uniformes).
            $imagen = null;
            if (method_exists($p, 'getFirstMediaUrl')) {
                $imagen = $p->getFirstMediaUrl('imagen') ?: null;
            }
            if (! $imagen) {
                // Placeholder generado: color estable por hash de la referencia,
                //   letra inicial visible. Se sirve inline como SVG data URI.
                $ref = strtoupper($p->referencia ?: 'GB');
                $letra = mb_substr($p->nombre ?? $ref, 0, 1);
                $paleta = ['#FDA4AF', '#93C5FD', '#86EFAC', '#FDE68A', '#C4B5FD', '#F9A8D4', '#FCA5A5'];
                $color = $paleta[crc32($ref) % count($paleta)];
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200"><rect width="200" height="200" fill="'.$color.'"/><text x="100" y="130" font-family="system-ui,sans-serif" font-size="90" text-anchor="middle" fill="rgba(255,255,255,.9)" font-weight="700">'.htmlspecialchars($letra).'</text></svg>';
                $imagen = 'data:image/svg+xml;base64,'.base64_encode($svg);
            }

            return [
                'id' => $p->id,
                'referencia' => $p->referencia,
                'nombre' => $p->nombre,
                'imagen' => $imagen,
                'variantes_count' => $p->variantes->count(),
                'precio_desde' => $preciosVar->min() ?: null,
                'precio_hasta' => $preciosVar->max() ?: null,
                'precio_proveedor' => (float) $p->precio_proveedor,
            ];
        })->values();

        return Inertia::render('Portal/Catalogo/Index', [
            'productos' => $data,
            'meta' => [
                'total' => $productos->total(),
                'per_page' => $productos->perPage(),
                'current_page' => $productos->currentPage(),
                'last_page' => $productos->lastPage(),
            ],
            'filtros' => ['q' => $q, 'marca_id' => $marca ?: null],
            'lista_nombre' => $cliente->listaPrecios?->nombre,
            'marcas' => Cache::remember('catalogo.marcas.activas', 300, fn () =>
                \App\Modules\Catalogo\Models\Marca::orderBy('nombre')->get(['id', 'nombre'])),
        ]);
    }

    public function show(Request $request, int $producto): Response
    {
        $cliente = $request->user('cliente');
        $listaId = (int) ($cliente->lista_precios_id ?? 0);

        $p = Producto::with(['variantes' => fn ($q) => $q->orderBy('talla')->orderBy('color_nombre')])
            ->findOrFail($producto);
        abort_unless($p->activo, 404);

        $precios = $listaId
            ? PrecioVariante::whereIn('variante_id', $p->variantes->pluck('id'))
                ->where('lista_id', $listaId)
                ->where(function ($w) {
                    $w->whereNull('vigente_hasta')->orWhere('vigente_hasta', '>=', now()->toDateString());
                })
                ->pluck('precio', 'variante_id')
                ->all()
            : [];

        return Inertia::render('Portal/Catalogo/Show', [
            'producto' => [
                'id' => $p->id,
                'referencia' => $p->referencia,
                'nombre' => $p->nombre,
                'descripcion' => $p->descripcion,
                'requiere_talla' => (bool) $p->requiere_talla,
            ],
            'variantes' => $p->variantes->map(fn ($v) => [
                'id' => $v->id,
                'color' => $v->color_nombre,
                'diseno' => $v->diseno_nombre,
                'talla' => $v->talla,
                'codigo_barras' => $v->codigo_barras,
                'precio' => (float) ($precios[$v->id] ?? 0),
            ])->values(),
        ]);
    }
}
