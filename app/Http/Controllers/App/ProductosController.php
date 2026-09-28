<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\CatalogoClase;
use App\Modules\Catalogo\Models\CatalogoGrupo;
use App\Modules\Catalogo\Models\CatalogoLinea;
use App\Modules\Catalogo\Models\CatalogoSubgrupo;
use App\Modules\Dropi\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sprint 4 · G.3 · CRUD de Productos en Vue con formato SIIGO 4 pestañas.
 *
 * Basado en doc oficial SIIGO Ilimitada · Kardex → Referencias:
 * https://ilimitada.portaldeclientes.siigo.com/archivos-kardex-referencias/
 *
 * Reutiliza el modelo Producto ya existente (Filament sigue funcionando).
 * Ruta: /app/catalogo/productos
 */
class ProductosController extends Controller implements HasMiddleware
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

    public function index(Request $r): Response
    {
        $q = Producto::query()->with(['marca:id,nombre', 'categoriaMaestra:id,nombre', 'linea:id,codigo,nombre']);

        if ($busca = trim((string) $r->query('q', ''))) {
            $q->where(function ($qq) use ($busca) {
                $qq->where('referencia', 'like', '%'.$busca.'%')
                   ->orWhere('nombre', 'like', '%'.$busca.'%')
                   ->orWhere('siigo_code', 'like', $busca.'%');
            });
        }
        if ($r->filled('activo')) $q->where('activo', $r->boolean('activo'));
        if ($lineaId = $r->query('linea_id')) $q->where('linea_id', (int) $lineaId);
        if ($r->boolean('sin_siigo')) $q->whereNull('siigo_id');

        return Inertia::render('Catalogo/Productos', [
            'filtros' => [
                'q' => $r->query('q', ''),
                'activo' => $r->query('activo', ''),
                'linea_id' => $r->query('linea_id', ''),
                'sin_siigo' => $r->boolean('sin_siigo'),
            ],
            // QA-FIX #13 · cache KPIs 60s · 4 counts fullscan = 400ms del render.
            'kpis' => \Illuminate\Support\Facades\Cache::remember('productos.kpis.v1', 60, fn () => [
                'total' => Producto::count(),
                'activos' => Producto::where('activo', true)->count(),
                'sin_siigo' => Producto::whereNull('siigo_id')->where('activo', true)->count(),
                'protegidos' => Producto::where('proteger_precio', true)->count(),
            ]),
            'productos' => $q->orderByDesc('id')->paginate(30)->through(fn ($p) => $this->serializarBrief($p)),
            'lineas' => CatalogoLinea::where('activa', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function show(Producto $producto): Response
    {
        $producto->load([
            'marca:id,nombre', 'categoriaMaestra:id,nombre', 'coleccion:id,nombre',
            'unidadMedida:id,codigo,nombre', 'unidadCompra:id,codigo,nombre',
            'impuesto:id,nombre,porcentaje',
            'linea:id,codigo,nombre', 'grupo:id,codigo,nombre',
            'subgrupo:id,codigo,nombre', 'clase:id,codigo,nombre',
            'variantes',
            'accesorios:id,referencia,nombre',
            'sustitutos:id,referencia,nombre',
        ]);

        return Inertia::render('Catalogo/ProductoShow', [
            'producto' => $this->serializarCompleto($producto),
            'catalogos' => $this->catalogosParaForm(),
        ]);
    }

    public function crearForm(): Response
    {
        return Inertia::render('Catalogo/ProductoShow', [
            'producto' => null,
            'catalogos' => $this->catalogosParaForm(),
        ]);
    }

    public function guardar(Request $r, ?Producto $producto = null): RedirectResponse
    {
        $data = $r->validate([
            'referencia' => ['required', 'string', 'max:100'],
            'nombre' => ['required', 'string', 'max:200'],
            'descripcion' => ['nullable', 'string'],
            'descripcion_ampliada' => ['nullable', 'string'],
            'ficha_tecnica' => ['nullable', 'string'],
            'activo' => ['boolean'],
            'marca_id' => ['nullable', 'integer', 'exists:marcas,id'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'coleccion_id' => ['nullable', 'integer', 'exists:colecciones,id'],
            'unidad_medida_id' => ['nullable', 'integer', 'exists:unidades_medida,id'],
            'unidad_compra_id' => ['nullable', 'integer', 'exists:unidades_medida,id'],
            'impuesto_id' => ['nullable', 'integer', 'exists:impuestos,id'],
            'linea_id' => ['nullable', 'integer', 'exists:catalogo_lineas,id'],
            'grupo_id' => ['nullable', 'integer', 'exists:catalogo_grupos,id'],
            'subgrupo_id' => ['nullable', 'integer', 'exists:catalogo_subgrupos,id'],
            'clase_id' => ['nullable', 'integer', 'exists:catalogo_clases,id'],
            'posicion_arancelaria' => ['nullable', 'string', 'max:20'],
            'factor_conversion' => ['nullable', 'numeric', 'min:0'],
            'rentabilidad_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'reposicion_max_dias' => ['nullable', 'integer', 'min:0'],
            'descuento_default_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'valor_gasto_venta_niif' => ['nullable', 'numeric', 'min:0'],
            'valor_neto_realizable_niif' => ['nullable', 'numeric', 'min:0'],
            'precio_proveedor' => ['nullable', 'numeric', 'min:0'],
            'peso_gr' => ['nullable', 'numeric', 'min:0'],
            'alto_cm' => ['nullable', 'numeric', 'min:0'],
            'ancho_cm' => ['nullable', 'numeric', 'min:0'],
            'largo_cm' => ['nullable', 'numeric', 'min:0'],
            'proteger_precio' => ['boolean'],
            'maneja_lotes' => ['boolean'],
            'maneja_seriales' => ['boolean'],
            'es_estadistico' => ['boolean'],
            'requiere_talla' => ['boolean'],
            'es_set' => ['boolean'],
            'neto' => ['boolean'],
            'desglose_stock' => ['boolean'],
            'stock_directo' => ['nullable', 'numeric', 'min:0'],
            // Cuentas contables
            'cta_ingreso' => ['nullable', 'string', 'max:30'],
            'cta_iva_venta' => ['nullable', 'string', 'max:30'],
            'cta_costo' => ['nullable', 'string', 'max:30'],
            'cta_inventario' => ['nullable', 'string', 'max:30'],
            'cta_devolucion' => ['nullable', 'string', 'max:30'],
            'cta_descuento' => ['nullable', 'string', 'max:30'],
            'centro_costo' => ['nullable', 'string', 'max:30'],
            'notas_contables' => ['nullable', 'string'],
            'accesorios' => ['nullable', 'array'],
            'accesorios.*.id' => ['integer', 'exists:productos,id'],
            'accesorios.*.cantidad' => ['integer', 'min:1'],
            'sustitutos' => ['nullable', 'array'],
            'sustitutos.*' => ['integer', 'exists:productos,id'],
        ]);

        $accesorios = $data['accesorios'] ?? [];
        $sustitutos = $data['sustitutos'] ?? [];
        unset($data['accesorios'], $data['sustitutos']);

        if ($producto && $producto->exists) {
            $producto->fill($data)->save();
        } else {
            $producto = Producto::create($data + ['activo' => true]);
        }

        // Sincronizar M:M accesorios y sustitutos.
        $accesoriosSync = collect($accesorios)->mapWithKeys(fn ($a) => [$a['id'] => ['cantidad_default' => $a['cantidad'] ?? 1]])->all();
        $producto->accesorios()->sync($accesoriosSync);
        $producto->sustitutos()->sync($sustitutos);

        return redirect()->route('app.catalogo.productos.show', $producto->id)
            ->with('flash', ['type' => 'success', 'message' => "Producto {$producto->referencia} guardado."]);
    }

    public function eliminar(Producto $producto): RedirectResponse
    {
        if ($producto->movimientos()->exists()) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'No se puede eliminar: el producto tiene movimientos de kardex.',
            ]);
        }
        $ref = $producto->referencia;
        $producto->delete();
        return redirect()->route('app.catalogo.productos')
            ->with('flash', ['type' => 'success', 'message' => "Producto {$ref} eliminado."]);
    }

    /** Búsqueda AJAX para el selector M:M de accesorios/sustitutos. */
    public function buscar(Request $r): JsonResponse
    {
        $q = trim((string) $r->query('q', ''));
        if (mb_strlen($q) < 2) return response()->json([]);
        return response()->json(
            Producto::where('activo', true)
                ->where(function ($qq) use ($q) {
                    $qq->where('referencia', 'like', '%'.$q.'%')->orWhere('nombre', 'like', '%'.$q.'%');
                })
                ->limit(15)
                ->get(['id', 'referencia', 'nombre'])
                ->all()
        );
    }

    /** Grupos filtrados por línea (dropdown en cascada). */
    public function grupos(Request $r): JsonResponse
    {
        $lineaId = (int) $r->query('linea_id', 0);
        return response()->json(
            CatalogoGrupo::when($lineaId, fn ($q) => $q->where('linea_id', $lineaId))
                ->where('activa', true)->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'linea_id'])->all()
        );
    }

    public function subgrupos(Request $r): JsonResponse
    {
        $grupoId = (int) $r->query('grupo_id', 0);
        return response()->json(
            CatalogoSubgrupo::when($grupoId, fn ($q) => $q->where('grupo_id', $grupoId))
                ->where('activa', true)->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'grupo_id'])->all()
        );
    }

    public function clases(Request $r): JsonResponse
    {
        $subgrupoId = (int) $r->query('subgrupo_id', 0);
        return response()->json(
            CatalogoClase::when($subgrupoId, fn ($q) => $q->where('subgrupo_id', $subgrupoId))
                ->where('activa', true)->orderBy('codigo')
                ->get(['id', 'codigo', 'nombre', 'subgrupo_id'])->all()
        );
    }

    private function serializarBrief(Producto $p): array
    {
        return [
            'id' => $p->id,
            'referencia' => $p->referencia,
            'nombre' => $p->nombre,
            'marca' => $p->marca?->nombre,
            'categoria' => $p->categoriaMaestra?->nombre,
            'linea' => $p->linea ? "{$p->linea->codigo} · {$p->linea->nombre}" : null,
            'precio_proveedor' => (float) $p->precio_proveedor,
            'activo' => (bool) $p->activo,
            'siigo_id' => $p->siigo_id,
            'siigo_code' => $p->siigo_code,
            'proteger_precio' => (bool) $p->proteger_precio,
        ];
    }

    private function serializarCompleto(Producto $p): array
    {
        return array_merge($this->serializarBrief($p), [
            'descripcion' => $p->descripcion,
            'descripcion_ampliada' => $p->descripcion_ampliada,
            'ficha_tecnica' => $p->ficha_tecnica,
            'marca_id' => $p->marca_id,
            'categoria_id' => $p->categoria_id,
            'coleccion_id' => $p->coleccion_id,
            'unidad_medida_id' => $p->unidad_medida_id,
            'unidad_compra_id' => $p->unidad_compra_id,
            'impuesto_id' => $p->impuesto_id,
            'linea_id' => $p->linea_id,
            'grupo_id' => $p->grupo_id,
            'subgrupo_id' => $p->subgrupo_id,
            'clase_id' => $p->clase_id,
            'posicion_arancelaria' => $p->posicion_arancelaria,
            'factor_conversion' => (float) $p->factor_conversion,
            'rentabilidad_pct' => (float) $p->rentabilidad_pct,
            'reposicion_max_dias' => $p->reposicion_max_dias,
            'descuento_default_pct' => (float) $p->descuento_default_pct,
            'valor_gasto_venta_niif' => (float) $p->valor_gasto_venta_niif,
            'valor_neto_realizable_niif' => (float) $p->valor_neto_realizable_niif,
            'peso_gr' => (float) $p->peso_gr,
            'alto_cm' => (float) $p->alto_cm,
            'ancho_cm' => (float) $p->ancho_cm,
            'largo_cm' => (float) $p->largo_cm,
            'maneja_lotes' => (bool) $p->maneja_lotes,
            'maneja_seriales' => (bool) $p->maneja_seriales,
            'es_estadistico' => (bool) $p->es_estadistico,
            'requiere_talla' => (bool) $p->requiere_talla,
            'es_set' => (bool) $p->es_set,
            'neto' => (bool) $p->neto,
            'desglose_stock' => (bool) $p->desglose_stock,
            'stock_directo' => (float) $p->stock_directo,
            'cta_ingreso' => $p->cta_ingreso,
            'cta_iva_venta' => $p->cta_iva_venta,
            'cta_costo' => $p->cta_costo,
            'cta_inventario' => $p->cta_inventario,
            'cta_devolucion' => $p->cta_devolucion,
            'cta_descuento' => $p->cta_descuento,
            'centro_costo' => $p->centro_costo,
            'notas_contables' => $p->notas_contables,
            'variantes_count' => $p->variantes->count(),
            'accesorios' => $p->accesorios->map(fn ($a) => [
                'id' => $a->id, 'referencia' => $a->referencia, 'nombre' => $a->nombre,
                'cantidad' => $a->pivot->cantidad_default,
            ])->all(),
            'sustitutos' => $p->sustitutos->map(fn ($s) => [
                'id' => $s->id, 'referencia' => $s->referencia, 'nombre' => $s->nombre,
            ])->all(),
        ]);
    }

    private function catalogosParaForm(): array
    {
        // QA-FIX #14 · Precargar toda la jerarquía como árbol para evitar 3 AJAX
        // secuenciales al elegir Línea→Grupo→Subgrupo→Clase (~2s cumulativo).
        // Vue filtra localmente ahora que tiene el árbol entero.
        $grupos = CatalogoGrupo::where('activa', true)->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'linea_id']);
        $subgrupos = CatalogoSubgrupo::where('activa', true)->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'grupo_id']);
        $clases = CatalogoClase::where('activa', true)->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre', 'subgrupo_id']);

        return [
            'marcas' => \App\Modules\Catalogo\Models\Marca::orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => \App\Modules\Catalogo\Models\Categoria::orderBy('nombre')->get(['id', 'nombre']),
            'colecciones' => \App\Modules\Catalogo\Models\Coleccion::orderBy('nombre')->get(['id', 'nombre']),
            'unidades' => \App\Modules\Catalogo\Models\UnidadMedida::orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'impuestos' => \App\Modules\Catalogo\Models\Impuesto::orderBy('nombre')->get(['id', 'nombre', 'porcentaje']),
            'lineas' => CatalogoLinea::where('activa', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']),
            'grupos' => $grupos,
            'subgrupos' => $subgrupos,
            'clases' => $clases,
        ];
    }
}
