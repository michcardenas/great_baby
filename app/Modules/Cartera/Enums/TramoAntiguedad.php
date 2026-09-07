<?php

namespace App\Modules\Cartera\Enums;

/**
 * §7 TO-BE Cartera — Semáforo de antigüedad de cartera.
 * Se calcula desde fecha_vencimiento hacia hoy (días de mora).
 */
enum TramoAntiguedad: string
{
    case AlDia = 'al_dia';
    case D0_30 = '0-30';
    case D31_59 = '31-59';
    case D60_89 = '60-89';
    case D90_119 = '90-119';
    case D120Mas = '120+';

    public static function delDias(int $diasMora): self
    {
        return match (true) {
            $diasMora <= 0 => self::AlDia,
            $diasMora <= 30 => self::D0_30,
            $diasMora <= 59 => self::D31_59,
            $diasMora <= 89 => self::D60_89,
            $diasMora <= 119 => self::D90_119,
            default => self::D120Mas,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::AlDia => 'Al día',
            self::D0_30 => '0–30 días',
            self::D31_59 => '31–59 días',
            self::D60_89 => '60–89 días',
            self::D90_119 => '90–119 días',
            self::D120Mas => '120+ días',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AlDia, self::D0_30 => 'success',
            self::D31_59 => 'warning',
            self::D60_89 => 'warning',
            self::D90_119 => 'danger',
            self::D120Mas => 'gray',
        };
    }

    public function colorHex(): string
    {
        return match ($this) {
            self::AlDia => '#10b981',
            self::D0_30 => '#22c55e',
            self::D31_59 => '#eab308',
            self::D60_89 => '#f97316',
            self::D90_119 => '#ef4444',
            self::D120Mas => '#7c3aed',
        };
    }
}
