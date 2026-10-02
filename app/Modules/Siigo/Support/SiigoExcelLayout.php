<?php

namespace App\Modules\Siigo\Support;

/**
 * FASE G3 · Mapeo bidireccional del archivo Excel "ModeloPersonalizado-
 * ImportacionProductos" oficial de SIIGO (34 columnas A..AH).
 *
 * Mantiene nombres EXACTOS de SIIGO en los encabezados. Export y Import
 * leen esta tabla como ÚNICA fuente de verdad. Cambiar aquí el nombre o
 * la columna propaga a ambos flujos.
 *
 * Formato de cada entrada:
 *   'A' => [header, campo_producto, tipo, obligatorio]
 *   - header: encabezado literal como lo pone SIIGO (fila 1 del xlsx)
 *   - campo: setter/getter en el modelo Producto, o 'enum', 'bool', 'precio'
 *   - tipo:   string | bool | int | decimal | enum:a,b,c | precio
 *   - obligatorio: true si SIIGO lo marca con "(obligatorio)"
 */
class SiigoExcelLayout
{
    public const COLS = [
        'A'  => ['Tipo de Producto (obligatorio)',               'tipo_siigo',          'enum:P-Producto,S-Servicio', true],
        'B'  => ['Categoría de Inventarios / Servicios (obligatorio)', 'categoria',    'string', true],
        'C'  => ['Código del Producto (obligatorio)',            'referencia',          'string', true],
        'D'  => ['Nombre del Producto / Servicio (obligatorio)', 'nombre',              'string', true],
        'E'  => ['¿Inventariable? (obligatorio)',                'stock_control',       'bool',   true],
        'F'  => ['Visible en facturas de venta',                 'visible_en_facturas', 'bool',   false],
        'G'  => ['Stock mínimo',                                 'stock_minimo',        'decimal', false],
        'H'  => ['Código Unidad de medida DIAN',                 'unidad_medida_codigo', 'string', false],
        'I'  => ['Unidad de Medida Impresión Factura',           'unit_label',          'string', false],
        'J'  => ['Referencia de Fábrica',                        'reference_fabrica',   'string', false],
        'K'  => ['Código de Barras',                             'barcode_padre',       'string', false],
        'L'  => ['Descripción Larga',                            'descripcion',         'string', false],
        'M'  => ['Código Impuesto Retención',                    'retencion_siigo_code', 'string', false],
        'N'  => ['Código Impuesto Cargo',                        'impuesto_cargo_code', 'string', false],
        'O'  => ['Código Impuesto Cargo Dos',                    'impuesto_cargo_dos_code', 'string', false],
        'P'  => ['Cant. de mililitros',                          'ml',                  'decimal', false],
        'Q'  => ['Tarifa',                                       'tarifa',              'decimal', false],
        'R'  => ['Valor Impuesto Cargo Dos',                     'valor_impuesto_dos',  'decimal', false],
        'S'  => ['¿Incluye IVA en Precio de Venta?',             'tax_included',        'bool',   false],
        // T..AA = las 8 listas de precio con nombres EXACTOS de SIIGO
        'T'  => ['Cliente Preferente',                           'precio:SIIGO_CLI_PREF', 'precio', false],
        'U'  => ['Asistente supervisor',                         'precio:SIIGO_AS_SUP',  'precio', false],
        'V'  => ['Supervisor',                                   'precio:SIIGO_SUP',     'precio', false],
        'W'  => ['Asistente Gerente',                            'precio:SIIGO_AS_GER',  'precio', false],
        'X'  => ['Gerente',                                      'precio:SIIGO_GER',     'precio', false],
        'Y'  => ['Retail',                                       'precio:SIIGO_RETAIL',  'precio', false],
        'Z'  => ['Precio de venta Publico',                      'precio:SIIGO_PVP',     'precio', false],
        'AA' => ['14.999',                                       'precio:SIIGO_14999',   'precio', false],
        'AB' => ['Otro1',                                        'otro_1',              'string', false],
        'AC' => ['Otro2',                                        'otro_2',              'string', false],
        'AD' => ['Otro3',                                        'otro_3',              'string', false],
        'AE' => ['Otro4',                                        'otro_4',              'string', false],
        'AF' => ['Código Arancelario',                           'posicion_arancelaria', 'string', false],
        'AG' => ['Marca',                                        'marca_nombre',        'string', false],
        'AH' => ['Modelo',                                       'modelo_siigo',        'string', false],
    ];

    public static function encabezados(): array
    {
        return array_values(array_map(fn ($c) => $c[0], self::COLS));
    }

    public static function letras(): array
    {
        return array_values(array_keys(self::COLS));
    }

    /** Castea un valor según su tipo declarado. */
    public static function castear(mixed $raw, string $tipo): mixed
    {
        $raw = is_string($raw) ? trim($raw) : $raw;
        if ($raw === '' || $raw === null) return null;

        if ($tipo === 'bool') {
            if (is_bool($raw)) return $raw;
            // Normaliza acentos y mayúsculas · acepta SI/sí/Sí/YES/TRUE/1.
            $v = str_replace(
                ['á','é','í','ó','ú','Á','É','Í','Ó','Ú'],
                ['a','e','i','o','u','A','E','I','O','U'],
                (string) $raw,
            );
            return in_array(strtoupper(trim($v)), ['SI', 'YES', 'TRUE', '1'], true);
        }
        if ($tipo === 'decimal' || $tipo === 'precio') {
            // Formato colombiano "1.234,56" → "1234.56" · formato US "1,234.56" también.
            $s = (string) $raw;
            $s = str_replace(['$', ' '], '', $s);
            if (preg_match('/,\d{1,2}$/', $s)) {
                // Formato COL "1.234,56" · última coma = decimal.
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
                // "50.000" / "1.234.567" · puntos como separador miles COL.
                $s = str_replace('.', '', $s);
            } else {
                // Formato US "1,234.56" o "1.5" · solo quito comas (miles US).
                $s = str_replace(',', '', $s);
            }
            return (float) $s;
        }
        if ($tipo === 'int') return (int) $raw;
        if (str_starts_with($tipo, 'enum:')) {
            $opciones = explode(',', substr($tipo, 5));
            return in_array((string) $raw, $opciones, true) ? (string) $raw : null;
        }
        return (string) $raw;
    }

    /** Formatea un valor para que viaje al Excel (SI/NO para bools, decimales con punto, etc.) */
    public static function formatear(mixed $valor, string $tipo): mixed
    {
        if ($valor === null) return null;
        if ($tipo === 'bool') return $valor ? 'SI' : 'NO';
        if ($tipo === 'decimal' || $tipo === 'precio') {
            return (float) $valor;
        }
        // Enum tipo_siigo · la BD guarda 'Product'/'Service' (API SIIGO) pero
        // el Excel SIIGO espera 'P-Producto'/'S-Servicio'.
        if (str_starts_with($tipo, 'enum:')) {
            $mapa = [
                'Product' => 'P-Producto',
                'Service' => 'S-Servicio',
                'ConsumerGood' => 'P-Producto',
            ];
            return $mapa[$valor] ?? (string) $valor;
        }
        return (string) $valor;
    }

    /** Inverso de formatear() · del valor Excel al valor BD. */
    public static function desformatear(mixed $raw, string $tipo): mixed
    {
        if (str_starts_with($tipo, 'enum:')) {
            $mapa = [
                'P-Producto' => 'Product',
                'S-Servicio' => 'Service',
            ];
            $limpio = is_string($raw) ? trim($raw) : $raw;
            if (isset($mapa[$limpio])) return $mapa[$limpio];
            return self::castear($raw, $tipo);
        }
        return self::castear($raw, $tipo);
    }
}
