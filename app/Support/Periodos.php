<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * §22 diseño Dropi — Selector de periodo agrupado.
 * Cortes / Días / Semana / Mes / Mes anterior / Rango personalizado.
 * "Por conciliar" ignora el periodo (regla especial §22).
 */
class Periodos
{
    public const HOY = 'hoy';
    public const AYER = 'ayer';
    public const SEMANA = 'semana';
    public const MES = 'mes';
    public const MES_ANTERIOR = 'mes_anterior';
    public const TRIMESTRE = 'trimestre';
    public const ANIO = 'anio';

    public static function opciones(): array
    {
        return [
            self::HOY => 'Hoy',
            self::AYER => 'Ayer',
            self::SEMANA => 'Últimos 7 días',
            self::MES => 'Este mes',
            self::MES_ANTERIOR => 'Mes anterior',
            self::TRIMESTRE => 'Últimos 90 días',
            self::ANIO => 'Este año',
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    public static function rango(?string $clave): array
    {
        $clave ??= self::MES;
        $now = CarbonImmutable::now();

        [$desde, $hasta, $label] = match ($clave) {
            self::HOY => [$now->startOfDay(), $now->endOfDay(), 'Hoy'],
            self::AYER => [$now->subDay()->startOfDay(), $now->subDay()->endOfDay(), 'Ayer'],
            self::SEMANA => [$now->subDays(7)->startOfDay(), $now->endOfDay(), 'Últimos 7 días'],
            self::MES_ANTERIOR => [$now->subMonth()->startOfMonth(), $now->subMonth()->endOfMonth(), 'Mes anterior'],
            self::TRIMESTRE => [$now->subDays(90)->startOfDay(), $now->endOfDay(), 'Últimos 90 días'],
            self::ANIO => [$now->startOfYear(), $now->endOfYear(), 'Este año'],
            default => [$now->startOfMonth(), $now->endOfMonth(), 'Este mes'],
        };

        return [$desde, $hasta, $label];
    }

    public static function actual(): string
    {
        return session('dropi.periodo', self::MES);
    }

    public static function guardar(string $clave): void
    {
        if (array_key_exists($clave, self::opciones())) {
            session(['dropi.periodo' => $clave]);
        }
    }
}
