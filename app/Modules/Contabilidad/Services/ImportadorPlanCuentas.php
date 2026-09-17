<?php

namespace App\Modules\Contabilidad\Services;

use App\Modules\Contabilidad\Models\PlanCuenta;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Importa el plan de cuentas de Silvia desde Excel (.xlsx) o CSV.
 *
 * Columnas: `codigo` y `nombre` obligatorias; `naturaleza` (debito|credito),
 * `permite_movimiento` (1/0, si/no) y `siigo_cuenta_id` opcionales.
 * Si no viene `permite_movimiento`, se infiere: una cuenta es de movimiento
 * cuando es HOJA (ninguna otra cuenta del archivo la tiene como prefijo).
 *
 * Idempotente: hace updateOrCreate por `codigo`, así que re-subir el archivo
 * corregido actualiza sin duplicar. Al final revincula la jerarquía padre→hijo.
 */
class ImportadorPlanCuentas
{
    private const MAX_FILAS = 20000;

    /**
     * @return array{creados:int, actualizados:int, errores:array<int,string>}
     */
    public function importar(string $rutaArchivo): array
    {
        $reader = $this->crearLector($rutaArchivo);
        $reader->open($rutaArchivo);

        $columnas = [];
        $registros = [];      // codigo => datos (última fila gana)
        $errores = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $esHeader = true;
            $i = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $i++;
                $cells = array_map(fn ($c) => trim((string) $c->getValue()), $row->getCells());

                if ($esHeader) {
                    $esHeader = false;
                    $columnas = array_map(fn ($h) => strtolower(trim($h)), $cells);
                    $header = $columnas;
                    if (! in_array('codigo', $header, true) || ! in_array('nombre', $header, true)) {
                        $reader->close();
                        return ['creados' => 0, 'actualizados' => 0, 'errores' => [
                            'Faltan columnas obligatorias: la plantilla debe tener al menos "codigo" y "nombre". '
                                . 'Descarga la plantilla oficial con el botón "Plantilla".',
                        ]];
                    }
                    continue;
                }

                if (empty(array_filter($cells))) {
                    continue;
                }

                $fila = @array_combine(
                    array_pad($columnas, count($cells), ''),
                    array_pad($cells, count($columnas), '')
                );

                $codigo = preg_replace('/\D/', '', (string) ($fila['codigo'] ?? ''));
                $nombre = self::sanitizar((string) ($fila['nombre'] ?? ''));

                if ($codigo === '') {
                    $errores[] = "Fila {$i}: código vacío o sin dígitos.";
                    continue;
                }
                if ($nombre === '') {
                    $errores[] = "Fila {$i} (cuenta {$codigo}): nombre vacío.";
                    continue;
                }

                $registros[$codigo] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'naturaleza' => self::naturalezaValida($fila['naturaleza'] ?? null),
                    'permite_movimiento' => self::boolOpcional($fila['permite_movimiento'] ?? null),
                    'siigo_cuenta_id' => self::sanitizar((string) ($fila['siigo_cuenta_id'] ?? '')) ?: null,
                    'activa' => self::boolOpcional($fila['activa'] ?? null) ?? true,
                ];

                if (count($registros) > self::MAX_FILAS) {
                    $reader->close();
                    return ['creados' => 0, 'actualizados' => 0, 'errores' => [
                        'El archivo excede el límite de ' . self::MAX_FILAS . ' cuentas.',
                    ]];
                }
            }
            break; // solo la primera hoja
        }
        $reader->close();

        if (empty($registros)) {
            return ['creados' => 0, 'actualizados' => 0, 'errores' => array_merge($errores, ['No se encontraron cuentas válidas para importar.'])];
        }

        // Inferir hojas (permite_movimiento) donde no venga explícito.
        $codigos = array_keys($registros);
        foreach ($registros as $codigo => &$datos) {
            if ($datos['permite_movimiento'] === null) {
                $esHoja = true;
                foreach ($codigos as $otro) {
                    if ($otro !== $codigo && str_starts_with($otro, $codigo) && strlen($otro) > strlen($codigo)) {
                        $esHoja = false;
                        break;
                    }
                }
                $datos['permite_movimiento'] = $esHoja;
            }
        }
        unset($datos);

        $creados = 0;
        $actualizados = 0;

        DB::transaction(function () use ($registros, &$creados, &$actualizados): void {
            foreach ($registros as $datos) {
                $cuenta = PlanCuenta::updateOrCreate(
                    ['codigo' => $datos['codigo']],
                    [
                        'nombre' => $datos['nombre'],
                        'naturaleza' => $datos['naturaleza'] ?? PlanCuenta::naturalezaPorClase(substr($datos['codigo'], 0, 1)),
                        'permite_movimiento' => $datos['permite_movimiento'],
                        'siigo_cuenta_id' => $datos['siigo_cuenta_id'],
                        'activa' => $datos['activa'],
                    ]
                );
                $cuenta->wasRecentlyCreated ? $creados++ : $actualizados++;
            }

            PlanCuenta::vincularPadres();
        });

        return ['creados' => $creados, 'actualizados' => $actualizados, 'errores' => $errores];
    }

    /**
     * Elige el lector según extensión. Para CSV auto-detecta el separador
     * (Excel en es-CO suele exportar con `;`).
     */
    private function crearLector(string $ruta): ReaderInterface
    {
        $ext = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));

        if ($ext === 'csv' || $ext === 'txt') {
            $options = new CsvOptions();
            $options->FIELD_DELIMITER = $this->detectarDelimitador($ruta);
            return new CsvReader($options);
        }

        return new XlsxReader();
    }

    private function detectarDelimitador(string $ruta): string
    {
        $primera = '';
        if ($h = @fopen($ruta, 'r')) {
            $primera = (string) fgets($h);
            fclose($h);
        }
        // BOM fuera.
        $primera = preg_replace('/^\xEF\xBB\xBF/', '', $primera);

        return substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    }

    /** naturaleza válida ('debito'/'credito') o null para que la derive la clase. */
    private static function naturalezaValida(?string $v): ?string
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        if (str_starts_with($v, 'deb') || $v === 'd') {
            return 'debito';
        }
        if (str_starts_with($v, 'cred') || str_starts_with($v, 'cré') || $v === 'c') {
            return 'credito';
        }
        return null;
    }

    private static function boolOpcional(?string $v): ?bool
    {
        $v = strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        if (in_array($v, ['1', 'si', 'sí', 'true', 'x', 'yes', 'y', 'verdadero'], true)) {
            return true;
        }
        if (in_array($v, ['0', 'no', 'false', 'n', 'falso'], true)) {
            return false;
        }
        return null;
    }

    /** Anti CSV-injection + corte de longitud. */
    private static function sanitizar(?string $v): string
    {
        if ($v === null) {
            return '';
        }
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        if (in_array(mb_substr($v, 0, 1), ['=', '+', '-', '@', "\t", "\r"], true)) {
            $v = "'" . $v;
        }
        return mb_substr($v, 0, 150);
    }
}
