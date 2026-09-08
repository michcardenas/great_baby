<?php

namespace App\Support;

/**
 * Convierte un número a letras en español (COP).
 * Uso: NumeroALetras::convertir(1234567.89)
 *   → "UN MILLÓN DOSCIENTOS TREINTA Y CUATRO MIL QUINIENTOS SESENTA Y SIETE PESOS CON 89/100 M/CTE"
 */
class NumeroALetras
{
    private const UNIDADES = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE', 'DIEZ',
        'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISÉIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE', 'VEINTE'];
    private const DECENAS = ['', '', 'VEINTI', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
    private const CENTENAS = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS',
        'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];

    public static function convertir(float $monto, string $moneda = 'PESOS'): string
    {
        $entero = (int) floor($monto);
        $centavos = (int) round(($monto - $entero) * 100);
        $letras = self::enteroALetras($entero);
        return trim("$letras $moneda CON " . str_pad((string) $centavos, 2, '0', STR_PAD_LEFT) . "/100 M/CTE");
    }

    private static function enteroALetras(int $n): string
    {
        if ($n === 0) return 'CERO';
        if ($n < 0) return 'MENOS ' . self::enteroALetras(-$n);

        if ($n >= 1_000_000_000) {
            $millares = intdiv($n, 1_000_000_000);
            $resto = $n % 1_000_000_000;
            $prefijo = $millares === 1 ? 'MIL' : (self::enteroALetras($millares) . ' MIL');
            $suf = $resto ? ' MILLONES ' . self::enteroALetras($resto) : ' MILLONES';
            return $prefijo . $suf;
        }
        if ($n >= 1_000_000) {
            $millones = intdiv($n, 1_000_000);
            $resto = $n % 1_000_000;
            $palabra = $millones === 1 ? 'UN MILLÓN' : (self::enteroALetras($millones) . ' MILLONES');
            return trim($palabra . ($resto ? ' ' . self::enteroALetras($resto) : ''));
        }
        if ($n >= 1_000) {
            $miles = intdiv($n, 1_000);
            $resto = $n % 1_000;
            $palabra = $miles === 1 ? 'MIL' : (self::enteroALetras($miles) . ' MIL');
            return trim($palabra . ($resto ? ' ' . self::enteroALetras($resto) : ''));
        }
        if ($n >= 100) {
            $centenas = intdiv($n, 100);
            $resto = $n % 100;
            $palabra = $n === 100 ? 'CIEN' : self::CENTENAS[$centenas];
            return trim($palabra . ($resto ? ' ' . self::enteroALetras($resto) : ''));
        }
        if ($n <= 20) return self::UNIDADES[$n];
        if ($n < 30) {
            // 21-29 → veintiuno, veintidós, etc.
            $u = $n - 20;
            return 'VEINTI' . strtolower(self::UNIDADES[$u]);
        }
        $decena = intdiv($n, 10);
        $unidad = $n % 10;
        return self::DECENAS[$decena] . ($unidad ? ' Y ' . self::UNIDADES[$unidad] : '');
    }
}
