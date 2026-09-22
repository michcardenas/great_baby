<?php

namespace App\Console\Commands;

use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use Database\Seeders\CategoriasClienteBebesSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * C-F7 · Comando para cargar el Excel de inventario del cliente Aracely.
 *
 *   php artisan inventario:cargar-excel-cliente
 *     --archivo="ruta/a/INVENTARIO DR REPORTE.xlsx"
 *     --bodega=1
 *     [--hoja=Hoja1] [--dry-run]
 *
 * Formato del Excel esperado:
 *   Fila 1: título ("INVENTARIO ACTUAL A ...")  → ignora
 *   Fila 2: headers ("Referencia | Descripcion | Existencia | Variación")
 *   Fila 3+:
 *     - Fila con solo col A no vacía → categoría actual (ej "ALIMENTACION")
 *     - Fila con las 4 cols → producto (crea/actualiza como agregado)
 *
 * Los productos se crean con:
 *   - desglose_stock = false  (modo agregado)
 *   - stock_directo   = existencia (se refleja también como movimiento de kardex
 *                       en la bodega indicada)
 *   - categoria_id    = la última categoría vista
 *   - nombre limpio (quitando el prefijo de referencia repetido)
 *   - variación se guarda en `descripcion` como texto informativo
 */
class CargarInventarioClienteExcel extends Command
{
    protected $signature = 'inventario:cargar-excel-cliente
        {--archivo= : Ruta absoluta al .xlsx del cliente}
        {--bodega= : ID de la bodega donde cargar el inventario inicial}
        {--hoja=Hoja1 : Nombre de la hoja del Excel a leer}
        {--dry-run : Solo muestra qué haría, no toca BD}
        {--force : Requerido en producción para confirmar la corrida}';

    protected $description = 'C-F7 · Carga inventario del cliente Aracely desde Excel (formato REPORTE) creando productos agregados con stock inicial en una bodega.';

    public function handle(): int
    {
        // C-F-QA5 · Fix CRÍTICO auditor roles: prevenir corrida accidental en prod.
        //   Sin este guard, cualquier ops con SSH puede correrlo y meter movs sin
        //   trazabilidad (el importador no llena user_id).
        if (app()->environment('production') && ! $this->option('force')) {
            $this->error('En producción requiere --force. Ejemplo: --force');
            return self::FAILURE;
        }

        $archivo = $this->option('archivo');
        $bodegaId = (int) $this->option('bodega');
        $hojaNombre = (string) $this->option('hoja');
        $dryRun = (bool) $this->option('dry-run');

        // ─── Validaciones de argumentos ───────────────────────────
        if (! $archivo || ! is_file($archivo)) {
            $this->error("--archivo no existe o no fue provisto: {$archivo}");
            return self::FAILURE;
        }
        if ($bodegaId <= 0 || ! InventarioUbicacion::whereKey($bodegaId)->exists()) {
            $this->error("--bodega debe ser un ID válido de InventarioUbicacion. Ubicaciones disponibles:");
            $this->table(['id', 'nombre'], InventarioUbicacion::query()->get(['id', 'nombre'])->toArray());
            return self::FAILURE;
        }

        // ─── Semilla de categorías (idempotente) ──────────────────
        //   Siempre se corre (incluso en dry-run) porque es idempotente y
        //   sin sembrar no hay categorías para resolver las filas del Excel.
        $this->info('Sembrando 9 categorías del cliente (idempotente)…');
        (new CategoriasClienteBebesSeeder())->setContainer(app())->setCommand($this)->run();

        // Mapa nombre → categoría para lookup rápido.
        $catsPorNombre = Categoria::all()->keyBy(fn ($c) => mb_strtoupper(trim($c->nombre)));

        // ─── Lectura del Excel ────────────────────────────────────
        $reader = new XlsxReader();
        $reader->open($archivo);

        $categoriaActual = null;
        $creados = 0;
        $actualizados = 0;
        $movsInsertados = 0;
        $filasIgnoradas = 0;
        $sinCategoria = 0;

        try {
            $callback = function () use (
                $reader, $hojaNombre, $bodegaId, $dryRun, $catsPorNombre,
                &$categoriaActual, &$creados, &$actualizados, &$movsInsertados,
                &$filasIgnoradas, &$sinCategoria
            ) {
                foreach ($reader->getSheetIterator() as $sheet) {
                    if ($sheet->getName() !== $hojaNombre) continue;

                    foreach ($sheet->getRowIterator() as $i => $row) {
                        if ($i <= 2) continue; // fila 1 (título) y fila 2 (headers)

                        $celdas = array_map(function ($c) {
                            $v = $c->getValue();
                            // El cliente pone fechas en la fila 1 (título) → openspout devuelve DateTimeImmutable.
                            if ($v instanceof \DateTimeInterface) return $v->format('Y-m-d');
                            return trim((string) $v);
                        }, $row->getCells());
                        [$ref, $desc, $existencia, $variacion] = array_pad($celdas, 4, '');

                        // Fila vacía completa → siguiente.
                        if ($ref === '' && $desc === '' && $existencia === '') {
                            $filasIgnoradas++;
                            continue;
                        }

                        // Fila de CATEGORÍA (solo col A, resto vacío).
                        if ($ref !== '' && $desc === '' && $existencia === '') {
                            $nombreCat = mb_strtoupper(trim($ref));
                            $categoriaActual = $catsPorNombre->get($nombreCat);
                            if (! $categoriaActual) {
                                $this->warn("  Categoría '{$nombreCat}' no encontrada · seedeando ad-hoc.");
                                if (! $dryRun) {
                                    $categoriaActual = Categoria::firstOrCreate(
                                        ['nombre' => $nombreCat],
                                        ['codigo' => mb_substr($nombreCat, 0, 4), 'activa' => true],
                                    );
                                    $catsPorNombre[$nombreCat] = $categoriaActual;
                                }
                            }
                            continue;
                        }

                        // Fila de PRODUCTO — pero antes filtrar filas basura del pie del Excel:
                        //   - "TOTAL EXISTENCIA" y variantes (fila de totales)
                        //   - fechas ISO en columna A (fila de fecha de emisión al final)
                        //   - descripción vacía (no es producto válido)
                        if ($ref === '') { $filasIgnoradas++; continue; }
                        if ($this->esBasuraDePie($ref, $desc)) { $filasIgnoradas++; continue; }
                        if ($desc === '') { $filasIgnoradas++; continue; }
                        if (! $categoriaActual) { $sinCategoria++; continue; }

                        $nombreLimpio = $this->limpiarNombre($desc, $ref);
                        $stock = (int) preg_replace('/[^0-9\-]/', '', $existencia);
                        $variacion = $variacion === '' || $variacion === 'NA' ? null : $variacion;

                        if ($dryRun) {
                            $this->line("  [DRY] {$ref} · {$nombreLimpio} · stock={$stock} · cat={$categoriaActual->nombre} · var=".($variacion ?: '—'));
                            continue;
                        }

                        // FIX CRÍTICO auditor · rechazar refs que ya existen como granular.
                        //   Si updateOrCreate intenta cambiar desglose_stock=true → false
                        //   sobre un producto con movimientos, el trigger de bloqueo
                        //   aborta TODA la transacción. Peor aún: si no tiene movs, el
                        //   toggle sí cambia y queda un producto con variantes colgando
                        //   pero en modo agregado (estado inconsistente).
                        //   Solución raíz: buscar primero, si existe granular saltar
                        //   con warning; solo update si ya es agregado.
                        $existente = Producto::where('referencia', $ref)->first();
                        if ($existente && $existente->desglose_stock) {
                            $this->warn("  ⚠️  Skip: '{$ref}' ya existe como GRANULAR (id {$existente->id}). Import solo actualiza productos agregados. Renombra la referencia si es un producto distinto.");
                            $filasIgnoradas++;
                            continue;
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
                                // Solo fijar precio si es creación (evita pisar precio real que Aracely cargó después).
                                ...($existente ? [] : ['precio_proveedor' => 0]),
                            ],
                        );

                        if ($producto->wasRecentlyCreated) { $creados++; } else { $actualizados++; }

                        // Movimiento inicial en kardex — idempotente por (producto, ubicacion, tipo, notas).
                        if ($stock > 0) {
                            $notaMarker = "C-F7 · Carga inicial Excel cliente · ref {$ref}";
                            $existe = InventarioMovimiento::query()
                                ->where('producto_id', $producto->id)
                                ->whereNull('variante_id')
                                ->where('ubicacion_id', $bodegaId)
                                ->where('notas', $notaMarker)
                                ->exists();
                            if (! $existe) {
                                InventarioMovimiento::create([
                                    'producto_id' => $producto->id,
                                    'variante_id' => null,
                                    'ubicacion_id' => $bodegaId,
                                    'tipo' => 'carga_inicial_cliente',
                                    'cantidad' => $stock,
                                    'notas' => $notaMarker,
                                ]);
                                $movsInsertados++;
                            }
                        }
                    }
                }
            };

            if ($dryRun) {
                $callback();
            } else {
                DB::transaction($callback);
            }
        } finally {
            $reader->close();
        }

        // ─── Reporte final ────────────────────────────────────────
        $this->newLine();
        $this->info($dryRun ? '════ DRY RUN ════' : '════ CARGA COMPLETADA ════');
        $this->table(
            ['métrica', 'valor'],
            [
                ['Productos creados',            $creados],
                ['Productos actualizados',       $actualizados],
                ['Movs de kardex insertados',    $movsInsertados],
                ['Filas ignoradas (vacías)',     $filasIgnoradas],
                ['Filas sin categoría (skip)',   $sinCategoria],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * Limpia el nombre quitando el prefijo de referencia repetido.
     * Ej: descripción "202510-5 LAVA TETERO" con ref "202510-5" → "LAVA TETERO".
     */
    private function limpiarNombre(string $desc, string $ref): string
    {
        $limpio = trim($desc);
        if ($ref !== '' && str_starts_with($limpio, $ref)) {
            $limpio = trim(mb_substr($limpio, mb_strlen($ref)));
        }
        return $limpio !== '' ? $limpio : $ref;
    }

    /**
     * Filtra filas del pie del Excel que no son productos:
     *   - "134 | TOTAL EXISTENCIA : | 8554" (totales)
     *   - "2026-09-19 | 1899-12-30 | 0" (fecha de emisión residual)
     * Se detectan porque `desc` contiene tokens típicos de esas filas.
     */
    private function esBasuraDePie(string $ref, string $desc): bool
    {
        $descUpper = mb_strtoupper($desc);
        if (str_contains($descUpper, 'TOTAL EXISTENCIA')) return true;
        if (str_contains($descUpper, 'TOTAL EXISTENCI')) return true; // por si trunca
        // Fila de fecha: ambos ref y desc son fechas ISO (YYYY-MM-DD).
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $ref) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $desc)) {
            return true;
        }
        return false;
    }
}
