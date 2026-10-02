<?php

namespace App\Modules\Siigo\Services;

use App\Modules\Catalogo\Models\ListaPrecios;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Siigo\Support\SiigoExcelLayout;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * FASE G1 + G5 · Export y Plantilla vacía del Excel ModeloPersonalizado
 * SIIGO. Un solo servicio que:
 *   - genera la plantilla vacía (solo encabezados + Hoja2 Listas)
 *   - exporta un set de productos del ERP con sus precios de las 8 listas
 *
 * El mapeo de columnas vive en SiigoExcelLayout · si SIIGO cambia el formato,
 * se toca ahí y ambas operaciones (export + import) se actualizan.
 */
class ProductosExcelService
{
    /**
     * Genera el .xlsx y devuelve la ruta temporal donde quedó guardado.
     *
     * @param  Collection<int, Producto>  $productos Si se omite, solo encabezados.
     * @return string Ruta absoluta al archivo generado.
     */
    public function generar(?Collection $productos = null): string
    {
        $sp = new Spreadsheet();
        $hojaDatos = $sp->getActiveSheet();
        $hojaDatos->setTitle('Datos');

        // Encabezados fila 1
        $letras = SiigoExcelLayout::letras();
        $headers = SiigoExcelLayout::encabezados();
        foreach ($letras as $i => $letra) {
            $hojaDatos->setCellValue("{$letra}1", $headers[$i]);
            $hojaDatos->getStyle("{$letra}1")->getFont()->setBold(true);
        }
        $hojaDatos->freezePane('A2');

        // Filas de datos
        $fila = 2;
        foreach (($productos ?? collect()) as $p) {
            foreach (SiigoExcelLayout::COLS as $letra => $meta) {
                [$header, $campo, $tipo] = $meta;
                $valor = $this->resolverValor($p, $campo);
                $valorFormat = SiigoExcelLayout::formatear($valor, $tipo);
                if ($valorFormat !== null) {
                    $hojaDatos->setCellValue("{$letra}{$fila}", $valorFormat);
                }
            }
            $fila++;
        }

        // Hoja 2 · Listas (enums para validación)
        $this->agregarHojaListas($sp);

        $tmp = tempnam(sys_get_temp_dir(), 'siigo-productos-') . '.xlsx';
        (new Xlsx($sp))->save($tmp);
        return $tmp;
    }

    /** Nombre sugerido para descarga. */
    public function nombreArchivo(bool $vacia = false): string
    {
        $sello = now()->format('Ymd-His');
        return $vacia
            ? "plantilla-productos-SIIGO-{$sello}.xlsx"
            : "productos-ERP-a-SIIGO-{$sello}.xlsx";
    }

    private function resolverValor(Producto $p, string $campo): mixed
    {
        // Precios de lista SIIGO · "precio:SIIGO_PVP" → tabla producto_precios o similar.
        if (str_starts_with($campo, 'precio:')) {
            $codigoLista = substr($campo, 7);
            return $this->precioLista($p, $codigoLista);
        }

        // Campos "virtuales" derivados.
        return match ($campo) {
            'unidad_medida_codigo' => optional($p->unidadMedida)->codigo_unece
                ?? optional($p->unidadMedida)->codigo
                ?? '94',  // DIAN "unidad"
            'marca_nombre'     => optional($p->marca)->nombre,
            'retencion_siigo_code' => optional($p->retencion)->codigo,
            'impuesto_cargo_code'  => optional($p->impuesto)->codigo,
            'impuesto_cargo_dos_code' => optional($p->impuestoCargoDos)->codigo,
            default            => $p->{$campo} ?? null,
        };
    }

    private function precioLista(Producto $p, string $codigoLista): ?float
    {
        // Si no hay tabla pivot producto_precios, usamos precio_proveedor como
        // fallback para la lista Retail; el resto quedan vacíos.
        if (! \Schema::hasTable('producto_precios')) {
            return $codigoLista === 'SIIGO_RETAIL' ? (float) $p->precio_proveedor : null;
        }

        $lista = ListaPrecios::where('codigo', $codigoLista)->first();
        if (! $lista) return null;

        $precio = \DB::table('producto_precios')
            ->where('producto_id', $p->id)
            ->where('lista_precios_id', $lista->id)
            ->value('precio');

        return $precio !== null ? (float) $precio : null;
    }

    private function agregarHojaListas(Spreadsheet $sp): void
    {
        $hoja = $sp->createSheet();
        $hoja->setTitle('Listas');

        $hoja->setCellValue('A1', 'Tipo de Producto');
        $hoja->setCellValue('A2', 'P-Producto');
        $hoja->setCellValue('A3', 'S-Servicio');

        $hoja->setCellValue('B1', 'Inventariable');
        $hoja->setCellValue('B2', 'SI');
        $hoja->setCellValue('B3', 'NO');

        try {
            $hoja->setCellValue('C1', 'Categorías');
            foreach (\DB::table('categorias')->orderBy('nombre')->pluck('nombre') as $i => $nom) {
                $hoja->setCellValue('C' . ($i + 2), $nom);
            }
        } catch (\Throwable) { /* tabla no existe en testing */ }

        try {
            $hoja->setCellValue('D1', 'UND DIAN');
            foreach (\DB::table('unidades_medida')->orderBy('nombre')->get(['nombre', 'codigo_unece', 'codigo']) as $i => $u) {
                $codigo = $u->codigo_unece ?: $u->codigo ?: '94';
                $hoja->setCellValue('D' . ($i + 2), "{$codigo} - {$u->nombre}");
            }
        } catch (\Throwable) {}

        try {
            $hoja->setCellValue('E1', 'Impuestos');
            foreach (\DB::table('impuestos')->orderBy('nombre')->get(['nombre', 'porcentaje']) as $i => $imp) {
                $hoja->setCellValue('E' . ($i + 2), "{$imp->nombre} ({$imp->porcentaje}%)");
            }
        } catch (\Throwable) {}
    }
}
