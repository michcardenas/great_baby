<?php

namespace App\Modules\Siigo\Services;

use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Catalogo\Models\Impuesto;
use App\Modules\Catalogo\Models\Marca;
use App\Modules\Catalogo\Models\UnidadMedida;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Support\SiigoExcelLayout;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * FASE G2 + G4 + G6 · Importa productos desde el xlsx ModeloPersonalizado
 * SIIGO (34 cols) al ERP.
 *
 * Flujo por fila:
 *   1. Validar obligatorias (A tipo, B categoría, C código, D nombre, E inv.)
 *   2. Normalizar valores (desformatear según tipo)
 *   3. Merge por `code` (col C):
 *       - existe en productos → UPDATE
 *       - no existe           → CREATE
 *   4. El Observer dispara PushProductoASiigo automáticamente (G4).
 *   5. Si falla, registra la fila en el reporte de errores.
 *
 * Al final genera un xlsx con 2 hojas:
 *   - Procesados: fila original + ID local + estado
 *   - Errores:    fila original + motivo
 */
class ProductosImportService
{
    private array $procesados = [];
    private array $errores = [];
    private int $nuevos = 0;
    private int $actualizados = 0;

    /**
     * @return array{procesados:int, nuevos:int, actualizados:int, errores:int, reporte_path:?string}
     */
    public function importar(string $xlsxPath): array
    {
        // PROD-2 · abrimos un lote en la Bandeja de Importaciones (TalentMap
        // style) para que el modal muestre histórico y Aracely pueda ver
        // qué subió, cuántas filas pasaron y cuántas fallaron.
        // Fallback a user id=1 cuando no hay sesión (artisan command, tinker,
        // tests): la tabla exige user_id NOT NULL y preferimos no fallar el
        // import por ese detalle · siempre existe el admin con id=1.
        $lote = \App\Models\ImportacionBandeja::create([
            'user_id'        => auth()->id() ?: 1,
            'tipo'           => 'productos-siigo',
            'archivo_nombre' => basename($xlsxPath),
            'estado'         => 'procesando',
            'iniciada_at'    => now(),
            'total_filas'    => 0,
            'procesadas'     => 0,
            'ok'             => 0,
            'errores'        => 0,
        ]);

        $sp = IOFactory::load($xlsxPath);
        $hoja = $sp->getSheetByName('Datos') ?: $sp->getActiveSheet();
        $maxRow = $hoja->getHighestRow();

        if ($maxRow < 2) {
            $lote->update(['estado' => 'completada', 'terminada_at' => now()]);
            return ['procesados' => 0, 'nuevos' => 0, 'actualizados' => 0, 'errores' => 0, 'reporte_path' => null];
        }

        // Pre-cargar maestras una vez (en vez de 1 query por fila).
        $cacheMaestras = $this->preCargarMaestras();

        // FASE F1.C4 · pre-detectar duplicados DE CÓDIGO en el mismo Excel y
        // SALTAR las filas repetidas en el procesamiento (antes se procesaban
        // igual y creaba/actualizaba 2× con el mismo código).
        $codigosVistos = [];
        $filasDuplicadas = [];  // nº de fila → true
        for ($r = 2; $r <= $maxRow; $r++) {
            $code = trim((string) $hoja->getCell("C{$r}")->getValue());
            if ($code === '') continue;
            if (isset($codigosVistos[$code])) {
                $filasDuplicadas[$r] = true;
                $this->errores[] = $this->filaPlana($hoja, $r) + ['_motivo' => "Fila {$r}: código `{$code}` duplicado con fila {$codigosVistos[$code]} del mismo Excel · SALTADA"];
                continue;
            }
            $codigosVistos[$code] = $r;
        }

        for ($r = 2; $r <= $maxRow; $r++) {
            if (isset($filasDuplicadas[$r])) continue;  // salta las duplicadas
            $this->procesarFila($hoja, $r, $cacheMaestras);
        }

        $reporte = $this->generarReporte();

        // Cierre del lote · estado completada si hubo 0 errores, fallida si
        // todas fallaron, parcial si hubo mezcla. Aracely lo ve en el modal.
        $totalProc = count($this->procesados);
        $totalErr  = count($this->errores);
        $totalFilas = $totalProc + $totalErr;
        $estado = $totalErr === 0
            ? 'completada'
            : ($totalProc === 0 ? 'fallida' : 'parcial');

        $lote->update([
            'total_filas'  => $totalFilas,
            'procesadas'   => $totalFilas,
            'ok'           => $totalProc,
            'errores'      => $totalErr,
            'estado'       => $estado,
            'terminada_at' => now(),
        ]);

        return [
            'procesados' => $totalProc,
            'nuevos' => $this->nuevos,
            'actualizados' => $this->actualizados,
            'errores' => $totalErr,
            'reporte_path' => $reporte,
            'lote_id' => $lote->id,
        ];
    }

    private function procesarFila($hoja, int $r, array $cache): void
    {
        $raw = $this->filaPlana($hoja, $r);

        // 1 · Validar obligatorias.
        foreach (['A', 'B', 'C', 'D'] as $letra) {
            if (trim((string) ($raw[$letra] ?? '')) === '') {
                $this->errores[] = $raw + ['_motivo' => "Fila {$r}: columna {$letra} es obligatoria y está vacía"];
                return;
            }
        }

        // 2 · Normalizar.
        $datos = [];
        $precios = [];
        $marcaNombre = null;
        $categoriaNombre = null;
        $unidadCodigo = null;
        $retencionCode = null;
        $impuestoCargoCode = null;
        $impuestoCargoDosCode = null;

        foreach (SiigoExcelLayout::COLS as $letra => $meta) {
            [, $campo, $tipo] = $meta;
            $valor = SiigoExcelLayout::desformatear($raw[$letra] ?? null, $tipo);
            if ($valor === null) continue;

            if (str_starts_with($campo, 'precio:')) {
                $precios[substr($campo, 7)] = $valor;
                continue;
            }

            // Campos "virtuales" van a resolver FK locales.
            switch ($campo) {
                case 'categoria':             $categoriaNombre = $valor; $datos['categoria'] = $valor; break;
                case 'marca_nombre':          $marcaNombre = $valor; break;
                case 'unidad_medida_codigo':  $unidadCodigo = $valor; break;
                case 'retencion_siigo_code':  $retencionCode = $valor; break;
                case 'impuesto_cargo_code':   $impuestoCargoCode = $valor; break;
                case 'impuesto_cargo_dos_code': $impuestoCargoDosCode = $valor; break;
                case 'otro_1': case 'otro_2': case 'otro_3': case 'otro_4': break;
                case 'ml': case 'tarifa': case 'valor_impuesto_dos': break;
                default: $datos[$campo] = $valor;
            }
        }

        try {
            DB::transaction(function () use ($r, $raw, &$datos, $categoriaNombre, $marcaNombre, $unidadCodigo, $retencionCode, $impuestoCargoCode, $impuestoCargoDosCode, $precios, &$cache) {
                $code = trim((string) $raw['C']);

                // Resolver FKs locales.
                if ($categoriaNombre && isset($cache['categorias'][strtolower($categoriaNombre)])) {
                    $datos['categoria_id'] = $cache['categorias'][strtolower($categoriaNombre)];
                }
                // Si la marca del Excel no existe local · la creamos sobre la
                // marcha. Antes se ignoraba el nombre y el producto quedaba sin
                // marca, lo que confundía al cliente ("subí Baby Boutique y
                // marca aparece vacía").
                if ($marcaNombre) {
                    $key = strtolower($marcaNombre);
                    if (! isset($cache['marcas'][$key])) {
                        // `marcas.codigo` es NOT NULL UNIQUE (max 20).
                        // Generamos un slug corto a partir del nombre y lo
                        // desambiguamos si choca con otra marca existente.
                        $base = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $marcaNombre), 0, 18)) ?: 'MRC';
                        $codigo = $base;
                        $i = 1;
                        while (\DB::table('marcas')->where('codigo', $codigo)->exists()) {
                            $codigo = substr($base, 0, 18 - strlen((string) $i)) . $i;
                            $i++;
                        }
                        $nueva = Marca::firstOrCreate(
                            ['nombre' => $marcaNombre],
                            ['codigo' => $codigo, 'activa' => true],
                        );
                        $cache['marcas'][$key] = $nueva->id;
                    }
                    $datos['marca_id'] = $cache['marcas'][$key];
                }
                if ($unidadCodigo && isset($cache['unidades'][$unidadCodigo])) {
                    $datos['unidad_medida_id'] = $cache['unidades'][$unidadCodigo];
                }
                if ($retencionCode && isset($cache['impuestos'][$retencionCode])) {
                    $datos['retencion_siigo_id'] = $cache['impuestos'][$retencionCode];
                }
                if ($impuestoCargoCode && isset($cache['impuestos'][$impuestoCargoCode])) {
                    $datos['impuesto_id'] = $cache['impuestos'][$impuestoCargoCode];
                }
                if ($impuestoCargoDosCode && isset($cache['impuestos'][$impuestoCargoDosCode])) {
                    $datos['impuesto_cargo_dos_id'] = $cache['impuestos'][$impuestoCargoDosCode];
                }

                // Si vienen precios en el Excel, el producto necesita una
                // variante donde colgarlos (precios_variante.variante_id).
                // En modo "producto agregado" (sin desglose) el push a SIIGO
                // igual usa el padre, pero las variantes son el único lugar
                // donde la BD guarda precio_lista.  Forzamos `desglose_stock`
                // solo cuando hay precios para no romper flujos que suben
                // productos sin lista de precios.
                if (! empty($precios)) {
                    $datos['desglose_stock'] = true;
                }
                // Toma de precio_proveedor desde la lista más barata que llegue,
                // para que el listado muestre algo distinto a $0 y el push a
                // SIIGO tenga un fallback cuando SIIGO re-mapea posiciones.
                if (empty($datos['precio_proveedor']) && ! empty($precios)) {
                    $datos['precio_proveedor'] = min(array_filter(array_map('floatval', $precios)));
                }

                // G4 · merge por código (referencia).
                $existente = Producto::where('referencia', $code)->first();
                if ($existente) {
                    $existente->fill($datos)->save();
                    $prod = $existente;
                    $this->actualizados++;
                    $this->procesados[] = $raw + ['_estado' => 'actualizado', '_id' => $existente->id];
                } else {
                    $datos['referencia'] = $code;
                    $datos['activo'] = true;
                    // Nota · el push automático a SIIGO ocurre siempre que
                    // `FEATURE_SIIGO_PUSH_AUTO=true` (ver SiigoConfig). No
                    // necesitamos un flag por producto para encolarlos.
                    $prod = Producto::create($datos);
                    $this->nuevos++;
                    $this->procesados[] = $raw + ['_estado' => 'creado', '_id' => $prod->id];
                }

                // Precios → variante única (o la primera existente). Si no hay
                // variantes, auto-creamos una SKU-única con el codigo_barras
                // del Excel (K) o el referencia si falta.
                if (! empty($precios)) {
                    $this->guardarPrecios($prod, $precios, $raw, $cache);
                }
            });
        } catch (\Throwable $e) {
            $this->errores[] = $raw + ['_motivo' => "Fila {$r}: " . $e->getMessage()];
        }
    }

    /**
     * Guarda los precios del Excel en `precios_variante`. Si el producto no
     * tiene variantes, crea una "única" (SKU = codigo_barras ?: referencia)
     * para colgar los precios · consistente con cómo el push a SIIGO espera
     * encontrarlos.
     */
    private function guardarPrecios(Producto $prod, array $precios, array $raw, array &$cache): void
    {
        // Pre-cargar listas_precios indexadas por el `codigo` interno
        // (`SIIGO_CLI_PREF`, `SIIGO_RETAIL`, …) que es la clave del Excel.
        if (! isset($cache['listas_precios'])) {
            $cache['listas_precios'] = DB::table('listas_precios')
                ->whereNotNull('codigo')
                ->pluck('id', 'codigo')
                ->all();
        }

        $variante = $prod->variantes()->first();
        if (! $variante) {
            // `producto_variantes` no tiene `sku` · usa `codigo_barras` como
            // identificador único de SKU. Tomamos K del Excel si viene, sino
            // sintetizamos uno a partir de la referencia.
            $codigoBarras = (string) ($raw['K'] ?? '') ?: ($prod->referencia . '-U');
            $variante = $prod->variantes()->create([
                'codigo_barras' => $codigoBarras,
                'stock_minimo' => (int) ($prod->stock_minimo ?? 0),
            ]);
        }

        foreach ($precios as $codigoLista => $valor) {
            if (! isset($cache['listas_precios'][$codigoLista])) continue;
            $listaId = $cache['listas_precios'][$codigoLista];
            DB::table('precios_variante')->updateOrInsert(
                ['variante_id' => $variante->id, 'lista_id' => $listaId],
                [
                    'precio' => (float) $valor,
                    'vigente_desde' => now()->toDateString(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    private function preCargarMaestras(): array
    {
        return [
            'categorias' => Categoria::pluck('id', DB::raw('LOWER(nombre)'))->all(),
            'marcas' => Marca::pluck('id', DB::raw('LOWER(nombre)'))->all(),
            'unidades' => UnidadMedida::get()->mapWithKeys(fn ($u) => [
                ($u->codigo_unece ?: $u->codigo ?: $u->nombre) => $u->id,
            ])->all(),
            // Impuestos indexados por `codigo` (como SIIGO los pide en Excel).
            'impuestos' => Impuesto::whereNotNull('codigo')->pluck('id', 'codigo')->all(),
        ];
    }

    private function filaPlana($hoja, int $r): array
    {
        $out = [];
        foreach (SiigoExcelLayout::letras() as $letra) {
            $out[$letra] = $hoja->getCell("{$letra}{$r}")->getValue();
        }
        return $out;
    }

    private function generarReporte(): ?string
    {
        if (empty($this->procesados) && empty($this->errores)) return null;

        $sp = new Spreadsheet();

        // Hoja 1: Procesados
        $hojaOK = $sp->getActiveSheet();
        $hojaOK->setTitle('Procesados');
        $this->escribirHojaReporte($hojaOK, $this->procesados, ['_estado', '_id']);

        // Hoja 2: Errores
        $hojaErr = $sp->createSheet();
        $hojaErr->setTitle('Errores');
        $this->escribirHojaReporte($hojaErr, $this->errores, ['_motivo']);

        $tmp = tempnam(sys_get_temp_dir(), 'siigo-import-reporte-') . '.xlsx';
        (new Xlsx($sp))->save($tmp);
        return $tmp;
    }

    private function escribirHojaReporte($hoja, array $filas, array $extras): void
    {
        // Encabezados: cols originales + extras.
        $letras = SiigoExcelLayout::letras();
        $headers = SiigoExcelLayout::encabezados();
        foreach ($letras as $i => $letra) {
            $hoja->setCellValue("{$letra}1", $headers[$i]);
            $hoja->getStyle("{$letra}1")->getFont()->setBold(true);
        }
        // Extras después de AH.
        $colExtra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($letras) + 1);
        foreach ($extras as $i => $k) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($letras) + 1 + $i);
            $hoja->setCellValue("{$col}1", $k);
            $hoja->getStyle("{$col}1")->getFont()->setBold(true);
        }

        foreach ($filas as $r => $fila) {
            $row = $r + 2;
            foreach ($letras as $letra) {
                if (isset($fila[$letra])) {
                    $hoja->setCellValue("{$letra}{$row}", $fila[$letra]);
                }
            }
            foreach ($extras as $i => $k) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($letras) + 1 + $i);
                if (isset($fila[$k])) {
                    $hoja->setCellValue("{$col}{$row}", $fila[$k]);
                }
            }
        }
    }
}
