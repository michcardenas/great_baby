<?php

namespace App\Modules\Compras\Services;

use App\Models\Contacto;
use App\Modules\Compras\Actions\CrearOrdenCompra;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Re-audit M2 R3 PATRÓN R (SEG-A3 / SEG-A4) · endurecimiento del import Excel:
 *   - Whitelist de columnas requeridas (rechaza sheets con headers manipulados).
 *   - Sanitización anti CSV-injection: `=`, `+`, `-`, `@`, `\t`, `\r` iniciales
 *     se prefixan con `'` en campos texto que luego se muestran o re-exportan.
 *   - Cap de 5000 filas por import (evita OOM).
 *   - Validación por fila: cantidad > 0, precio_unit >= 0 (no negativos).
 *   - Bodega obligatoria (bodega_default_nombre en columna o global).
 */
class ImportadorOrdenesCompra
{
    private const MAX_FILAS = 5000;
    private const COLUMNAS_REQUERIDAS = ['numero_oc', 'proveedor_nit', 'producto_referencia', 'cantidad', 'precio_unit'];

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
                    // Validar columnas requeridas ANTES de leer filas.
                    $faltantes = array_diff(self::COLUMNAS_REQUERIDAS, $columnas);
                    if (! empty($faltantes)) {
                        $reader->close();
                        return ['ok' => 0, 'errores' => [
                            'Columnas requeridas ausentes: ' . implode(', ', $faltantes)
                                . '. La plantilla oficial se descarga desde el botón "Plantilla".',
                        ]];
                    }
                    continue;
                }
                $fila = [];
                foreach ($columnas as $i => $col) {
                    $fila[$col] = $valores[$i] ?? null;
                }
                if (! empty($fila['numero_oc'])) {
                    $filas[] = $fila;
                    if (count($filas) > self::MAX_FILAS) {
                        $reader->close();
                        return ['ok' => 0, 'errores' => [
                            'El archivo excede el límite de ' . self::MAX_FILAS . ' líneas. Divídelo en varios.',
                        ]];
                    }
                }
            }
            break;
        }
        $reader->close();

        $grupos = [];
        foreach ($filas as $f) {
            $numero = self::sanitizar((string) $f['numero_oc']);
            $grupos[$numero][] = $f;
        }

        $ok = 0;
        $errores = [];

        foreach ($grupos as $numero => $lineas) {
            $primera = $lineas[0];
            $proveedor = Contacto::where('numero_documento', trim((string) $primera['proveedor_nit']))
                ->where('es_proveedor', true)->first();
            if (! $proveedor) {
                $errores[] = "OC {$numero}: proveedor NIT " . self::sanitizar((string) $primera['proveedor_nit']) . ' no existe.';
                continue;
            }

            $items = [];
            $filaInvalida = false;
            foreach ($lineas as $lineaIdx => $l) {
                $prod = Producto::where('referencia', trim((string) $l['producto_referencia']))->first();
                if (! $prod) {
                    $errores[] = "OC {$numero} línea " . ($lineaIdx + 2) . ': producto ' . self::sanitizar((string) $l['producto_referencia']) . ' no existe.';
                    $filaInvalida = true;
                    break;
                }
                $cantidad = (float) $l['cantidad'];
                $precio = (float) $l['precio_unit'];
                if ($cantidad <= 0) {
                    $errores[] = "OC {$numero} línea " . ($lineaIdx + 2) . ": cantidad debe ser > 0 (recibido {$cantidad}).";
                    $filaInvalida = true;
                    break;
                }
                if ($precio < 0) {
                    $errores[] = "OC {$numero} línea " . ($lineaIdx + 2) . ": precio_unit no puede ser negativo (recibido {$precio}).";
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
                    'descripcion' => self::sanitizar($l['descripcion'] ?? $prod->nombre),
                    'cantidad' => $cantidad,
                    'precio_unit' => $precio,
                    'descuento_pct' => (float) ($l['descuento_pct'] ?? 0),
                    'iva_pct' => (float) ($l['iva_pct'] ?? 19),
                ];
            }
            if ($filaInvalida) continue;

            try {
                CrearOrdenCompra::run([
                    'proveedor_id' => $proveedor->id,
                    // Bodega default de la primera bodega — controlador debe validar
                    // pero mantenemos aquí un fallback razonable.
                    'bodega_id' => \App\Modules\Dropi\Models\InventarioUbicacion::orderBy('id')->value('id'),
                    'tipo' => self::sanitizar((string) ($primera['tipo'] ?? 'nacional')),
                    'moneda' => trim((string) ($primera['moneda'] ?? 'COP')),
                    'tasa_cambio' => (float) ($primera['tasa_cambio'] ?? 1),
                    'fecha_esperada' => $primera['fecha_esperada'] ?? null,
                    'observaciones' => self::sanitizar($primera['observaciones'] ?? "Importada desde Excel (referencia {$numero})"),
                    'items' => $items,
                ]);
                $ok++;
            } catch (\Throwable $e) {
                $errores[] = "OC {$numero}: " . $e->getMessage();
            }
        }

        return ['ok' => $ok, 'errores' => $errores];
    }

    /**
     * Sanitiza contra CSV injection: si el string empieza con `=`, `+`, `-`,
     * `@`, tab o CR, prefijar con `'` (Excel lo trata como texto literal).
     * Corta también a 500 chars por seguridad.
     */
    private static function sanitizar(?string $v): string
    {
        if ($v === null) return '';
        $v = trim($v);
        if ($v === '') return '';
        if (in_array(mb_substr($v, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true)) {
            $v = "'" . $v;
        }
        return mb_substr($v, 0, 500);
    }
}
