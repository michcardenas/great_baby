<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Catalogo\Models\Color;
use App\Modules\Catalogo\Models\Marca;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * FIL-E · CRUD maestras catálogo Vue (Marcas, Categorías, Colores)
 */
class CatalogoMaestrasController extends Controller implements HasMiddleware
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
        return Inertia::render('Catalogo/Maestras', [
            'marcas' => Marca::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'proveedor_id', 'activa']),
            'categorias' => Categoria::orderBy('nombre')->get(['id', 'padre_id', 'codigo', 'nombre', 'cuenta_puc_ingreso', 'cuenta_puc_costo', 'activa']),
            'colores' => Color::orderBy('nombre')->get(['id', 'codigo', 'nombre', 'hex', 'activo']),
        ]);
    }

    public function marcaGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer', 'exists:marcas,id'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'activa' => ['boolean'],
        ]);
        if ($data['id'] ?? null) Marca::findOrFail($data['id'])->update($data);
        else Marca::create($data);
        return back()->with('success', 'Marca guardada.');
    }

    public function marcaEliminar(int $marca): RedirectResponse
    {
        Marca::findOrFail($marca)->delete();
        return back()->with('success', 'Marca eliminada.');
    }

    public function categoriaGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer', 'exists:categorias,id'],
            'padre_id' => ['nullable', 'integer'],
            'codigo' => ['required', 'string', 'max:20'],
            'nombre' => ['required', 'string', 'max:100'],
            'cuenta_puc_ingreso' => ['nullable', 'string', 'max:20'],
            'cuenta_puc_costo' => ['nullable', 'string', 'max:20'],
            'activa' => ['boolean'],
        ]);
        if ($data['id'] ?? null) Categoria::findOrFail($data['id'])->update($data);
        else Categoria::create($data);
        return back()->with('success', 'Categoría guardada.');
    }

    public function categoriaEliminar(int $categoria): RedirectResponse
    {
        Categoria::findOrFail($categoria)->delete();
        return back()->with('success', 'Categoría eliminada.');
    }

    public function colorGuardar(Request $r): RedirectResponse
    {
        $data = $r->validate([
            'id' => ['nullable', 'integer', 'exists:colores,id'],
            'codigo' => ['required', 'string', 'max:10'],
            'nombre' => ['required', 'string', 'max:50'],
            'hex' => ['nullable', 'string', 'max:7'],
            'activo' => ['boolean'],
        ]);
        if ($data['id'] ?? null) Color::findOrFail($data['id'])->update($data);
        else Color::create($data);
        return back()->with('success', 'Color guardado.');
    }

    public function colorEliminar(int $color): RedirectResponse
    {
        Color::findOrFail($color)->delete();
        return back()->with('success', 'Color eliminado.');
    }
}
