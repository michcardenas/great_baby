<?php

namespace App\Modules\Compras\Services;

use App\Models\Contacto;
use App\Modules\Compras\Actions\CrearOrdenCompra;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use OpenSpout\Reader\XLSX\Reader;

class ImportadorOrdenesCompra
{
    /**
     * @return array{ok:int, errores:array<int,string>}
     */
    public function importar(string $rutaArchivo): array
    {
        $reader = new Reader();
        $reader->open($rutaArchivo);

        $filas = [];
        $columnas = [];
        $primeraFila = true;

        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $valores = $row->toArray();
                if ($primeraFila) {
                    $columnas = array_map(fn ($v) => strtolower(trim((string) $v)), $valores);
                    $primeraFila = false;
                    continue;
                }
                $fila = [];
                foreach ($columnas as $i => $col) {
                    $fila[$col] = $valores[$i] ?? null;
                }
                if (! empty($fila['numero_oc'])) {
                    $filas[] = $fila;
                }
            }
            break;
        }
        $reader->close();

        // Agrupar por número de OC
        $grupos = [];
        foreach ($filas as $f) {
            $numero = trim((string) $f['numero_oc']);
            $grupos[$numero][] = $f;
        }

        $ok = 0;
        $errores = [];

        foreach ($grupos as $numero => $lineas) {
            $primera = $lineas[0];
            $proveedor = Contacto::where('numero_documento', trim((string) $primera['proveedor_nit']))
                ->where('es_proveedor', true)->first();
            if (! $proveedor) {
                $errores[] = "OC {$numero}: proveedor NIT {$primera['proveedor_nit']} no existe.";
                continue;
            }

            $items = [];
            $filaInvalida = false;
            foreach ($lineas as $l) {
                $prod = Producto::where('referencia', trim((string) $l['producto_referencia']))->first();
                if (! $prod) {
                    $errores[] = "OC {$numero}: producto {$l['producto_referencia']} no existe.";
                    $filaInvalida = true;
                    break;
                }
                $variante = null;
                if (! empty($l['variante_codigo'])) {
                    $variante = ProductoVariante::where('codigo_barras', trim((string) $l['variante_codigo']))->first();
                }
                $items[] = [
                    'producto_id' => $prod->id,
                    'variante_id' => $variante?->id,
                    'descripcion' => $l['descripcion'] ?? $prod->nombre,
                    'cantidad' => (float) $l['cantidad'],
                    'precio_unit' => (float) $l['precio_unit'],
                    'descuento_pct' => (float) ($l['descuento_pct'] ?? 0),
                    'iva_pct' => (float) ($l['iva_pct'] ?? 19),
                ];
            }
            if ($filaInvalida) continue;

            try {
                CrearOrdenCompra::run([
                    'proveedor_id' => $proveedor->id,
                    'tipo' => trim((string) ($primera['tipo'] ?? 'nacional')),
                    'moneda' => trim((string) ($primera['moneda'] ?? 'COP')),
                    'tasa_cambio' => (float) ($primera['tasa_cambio'] ?? 1),
                    'fecha_esperada' => $primera['fecha_esperada'] ?? null,
                    'observaciones' => $primera['observaciones'] ?? "Importada desde Excel (referencia {$numero})",
                    'items' => $items,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $errores[] = "OC {$numero}: " . $e->getMessage();
            }
        }

        return ['ok' => $ok, 'errores' => $errores];
    }
}
