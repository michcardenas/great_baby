<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use Database\Seeders\CategoriasClienteBebesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Importador Excel del cliente Aracely desde UI · reutiliza la lógica del command
 *   `inventario:cargar-excel-cliente` sin exigir SSH. Aracely arrastra el .xlsx
 *   (formato REPORTE del cliente con filas de categoría + productos agregados)
 *   y el sistema crea 134 productos agregados con stock inicial en la bodega
 *   seleccionada.
 */
class ImportarInventarioClienteController extends Controller
{
    public function show()
    {
        abort_unless(auth()->user()?->esAracely(), 403);

        return Inertia::render('Inventario/ImportarCliente', [
            'bodegas' => InventarioUbicacion::query()
                ->where(fn ($q) => $q->where('activa', true)->orWhereNull('activa'))
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'codigo'])
                ->map(fn ($b) => ['id' => $b->id, 'label' => "{$b->nombre} ({$b->codigo})"]),
        ]);
    }

    public function procesar(Request $request)
    {
        abort_unless(auth()->user()?->esAracely(), 403);

        $data = $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'], // 10 MB
            'bodega_id' => ['required', 'integer', 'exists:inventario_ubicaciones,id'],
            'hoja' => ['nullable', 'string', 'max:60'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $path = $request->file('archivo')->getRealPath();
        $hoja = $data['hoja'] ?: 'Hoja1';
        $bodegaId = (int) $data['bodega_id'];
        $dryRun = (bool) ($data['dry_run'] ?? false);

        try {
            $resumen = $this->cargar($path, $bodegaId, $hoja, $dryRun);
        } catch (\Throwable $e) {
            return back()->withErrors([
                'archivo' => 'No pude leer el Excel: '.$e->getMessage()
                    .'. Verifica que el nombre de la hoja sea "'.$hoja.'".',
            ]);
        }

        $accion = $dryRun ? '✅ Simulación (dry-run) completada' : '✅ Inventario cargado correctamente';
        $detalle = "Productos creados: {$resumen['creados']} · actualizados: {$resumen['actualizados']} · "
            ."movimientos de kardex: {$resumen['movs']} · filas ignoradas: {$resumen['ignoradas']}"
            .($resumen['sin_cat'] > 0 ? " · SIN CATEGORÍA: {$resumen['sin_cat']}" : '')
            .($resumen['omitidos_granular'] > 0 ? " · saltados (ya existen como granular): {$resumen['omitidos_granular']}" : '');

        // Fix bug feedback UX · usar clave 'success' que sí está compartida por
        // HandleInertiaRequests. Antes usaba 'flash' custom → Vue no la veía y
        // Aracely no sabía si la carga funcionó.
        return back()
            ->with('success', $accion.' · '.$detalle)
            ->with('importResumen', [
                'accion' => $accion,
                'detalle' => $detalle,
                'creados' => $resumen['creados'],
                'actualizados' => $resumen['actualizados'],
                'movs' => $resumen['movs'],
                'ignoradas' => $resumen['ignoradas'],
                'sin_cat' => $resumen['sin_cat'],
                'omitidos_granular' => $resumen['omitidos_granular'],
                'dry_run' => $dryRun,
            ]);
    }

    /**
     * Núcleo de carga · lee el Excel del cliente y crea/actualiza productos agregados.
     * Idempotente sobre (producto, ubicacion, marker) para permitir re-corridas seguras.
     */
    protected function cargar(string $path, int $bodegaId, string $hojaNombre, bool $dryRun): array
    {
        (new CategoriasClienteBebesSeeder())->setContainer(app())->run();
        $catsPorNombre = Categoria::all()->keyBy(fn ($c) => mb_strtoupper(trim($c->nombre)));

        $reader = new XlsxReader();
        $reader->open($path);

        $categoriaActual = null;
        $creados = 0; $actualizados = 0; $movs = 0;
        $ignoradas = 0; $sinCat = 0; $omitidosGran = 0;

        try {
            $run = function () use (
                $reader, $hojaNombre, $bodegaId, $dryRun, &$catsPorNombre,
                &$categoriaActual, &$creados, &$actualizados, &$movs,
                &$ignoradas, &$sinCat, &$omitidosGran
            ) {
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($sheet->getName() !== $hojaNombre) continue;

                    foreach ($sheet->getRowIterator() as $i => $row) {
                        if ($i <= 2) continue;
                        $celdas = array_map(function ($c) {
                            $v = $c->getValue();
                            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
                            return trim((string) $v);
                        }, $row->getCells());
                        [$ref, $desc, $existencia, $variacion] = array_pad($celdas, 4, '');

                        if ($ref === '' && $desc === '' && $existencia === '') { $ignoradas++; continue; }

                        // Fila de categoría (solo col A).
                        if ($ref !== '' && $desc === '' && $existencia === '') {
                            $nombreCat = mb_strtoupper(trim($ref));
                            $categoriaActual = $catsPorNombre->get($nombreCat);
                            if (! $categoriaActual && ! $dryRun) {
                                $categoriaActual = Categoria::firstOrCreate(
                                    ['nombre' => $nombreCat],
                                    ['codigo' => mb_substr($nombreCat, 0, 4), 'activa' => true],
                                );
                                $catsPorNombre[$nombreCat] = $categoriaActual;
                            }
                            continue;
                        }

                        if ($ref === '') { $ignoradas++; continue; }
                        if ($this->esBasuraDePie($ref, $desc)) { $ignoradas++; continue; }
                        if ($desc === '') { $ignoradas++; continue; }
                        if (! $categoriaActual) { $sinCat++; continue; }

                        $nombreLimpio = $this->limpiarNombre($desc, $ref);
                        $stock = (int) preg_replace('/[^0-9\-]/', '', $existencia);
                        $variacion = ($variacion === '' || $variacion === 'NA') ? null : $variacion;

                        if ($dryRun) { continue; }

                        // Rechazo raíz: si ya existe como granular, no forzamos toggle.
                        $existente = Producto::where('referencia', $ref)->first();
                        if ($existente && $existente->desglose_stock) {
                            $omitidosGran++; continue;
                        }

                        $producto = Producto::updateOrCreate(
                            ['referencia' => $ref],
                            [
                                'nombre' => $nombreLimpio,
                                'categoria_id' => $categoriaActual->id,
                                'descripcion' => $variacion ? "Colores/variación: {$variacion}" : null,
                                'activo' => true,
                                'desglose_stock' => false,
                                'stock_directo' => $stock,
                                ...($existente ? [] : ['precio_proveedor' => 0]),
                            ],
                        );
                        if ($producto->wasRecentlyCreated) { $creados++; } else { $actualizados++; }

                        // Mov inicial en kardex — idempotente.
                        if ($stock > 0) {
                            $marker = "C-F7 · Carga inicial Excel cliente · ref {$ref}";
                            $existeMov = InventarioMovimiento::query()
                                ->where('producto_id', $producto->id)
                                ->whereNull('variante_id')
                                ->where('ubicacion_id', $bodegaId)
                                ->where('notas', $marker)
                                ->exists();
                            if (! $existeMov) {
                                InventarioMovimiento::create([
                                    'producto_id' => $producto->id,
                                    'variante_id' => null,
                                    'ubicacion_id' => $bodegaId,
                                    'tipo' => 'carga_inicial_cliente',
                                    'cantidad' => $stock,
                                    'notas' => $marker,
                                ]);
                                $movs++;
                            }
                        }
                    }
                }
            };

            if ($dryRun) { $run(); } else { DB::transaction($run); }
        } finally {
            $reader->close();
        }

        return compact('creados', 'actualizados', 'movs', 'ignoradas', 'sinCat', 'omitidosGran')
            + ['sin_cat' => $sinCat, 'omitidos_granular' => $omitidosGran];
    }

    private function limpiarNombre(string $desc, string $ref): string
    {
        $limpio = trim($desc);
        if ($ref !== '' && str_starts_with($limpio, $ref)) {
            $limpio = trim(mb_substr($limpio, mb_strlen($ref)));
        }
        return $limpio !== '' ? $limpio : $ref;
    }

    private function esBasuraDePie(string $ref, string $desc): bool
    {
        $descUpper = mb_strtoupper($desc);
        if (str_contains($descUpper, 'TOTAL EXISTENCIA')) return true;
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desc)) return true;
        return false;
    }
}
