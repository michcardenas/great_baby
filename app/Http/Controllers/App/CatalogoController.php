<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Catalogo\Models\Coleccion;
use App\Modules\Catalogo\Models\Color;
use App\Modules\Catalogo\Models\Impuesto;
use App\Modules\Catalogo\Models\ListaPrecios;
use App\Modules\Catalogo\Models\Marca;
use App\Modules\Catalogo\Models\Talla;
use App\Modules\Dropi\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller implements HasMiddleware
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

    public function index(Request $request): Response
    {
        $q = trim((string) $request->input('q', ''));

        $query = Producto::query()->with(['marca:id,nombre', 'categoriaMaestra:id,nombre'])->withCount('variantes');
        if ($q !== '') {
            $query->where(function ($qq) use ($q) {
                $qq->where('referencia', 'like', "%{$q}%")->orWhere('nombre', 'like', "%{$q}%");
            });
        }
        $paginado = $query->orderBy('referencia')->paginate(24);

        return Inertia::render('Catalogo/Index', [
            'productos' => $paginado->through(fn ($p) => [
                'id' => $p->id,
                'referencia' => $p->referencia,
                'nombre' => $p->nombre,
                'marca' => $p->marca?->nombre,
                'categoria' => $p->categoriaMaestra?->nombre,
                'precio_proveedor' => (float) $p->precio_proveedor,
                'activo' => (bool) $p->activo,
                'variantes' => (int) $p->variantes_count,
                'siigo_id' => $p->siigo_id,
                'tiene_cta_contable' => ! empty($p->cta_ingreso) || ! empty($p->cta_iva_venta),
            ])->withQueryString(),
            'filtros' => ['q' => $q],
            'maestras' => [
                'marcas' => Marca::count(),
                'categorias' => Categoria::count(),
                'colecciones' => Coleccion::count(),
                'colores' => Color::count(),
                'tallas' => Talla::count(),
                'impuestos' => Impuesto::count(),
                'listas_precios' => ListaPrecios::count(),
            ],
        ]);
    }
}
