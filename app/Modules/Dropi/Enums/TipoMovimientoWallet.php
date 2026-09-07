<?php

namespace App\Modules\Dropi\Enums;

/**
 * §17, §18 Diseño Dropi — Categorización de movimientos de la billetera Dropi.
 * Sin comparación contra extracto bancario; se confía en el reporte de la wallet.
 */
enum TipoMovimientoWallet: string
{
    case PagoGuia = 'pago_guia';
    case RetiroBanco = 'retiro_banco';
    case Indemnizacion = 'indemnizacion';
    case FleteGarantia = 'flete_garantia';
    case Tarjeta = 'tarjeta';
    case Otro = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::PagoGuia => 'Pago de guía',
            self::RetiroBanco => 'Retiro a banco',
            self::Indemnizacion => 'Indemnización',
            self::FleteGarantia => 'Flete de garantía',
            self::Tarjeta => 'Tarjeta de crédito',
            self::Otro => 'Otro',
        };
    }

    public function esGasto(): bool
    {
        return match ($this) {
            self::Indemnizacion, self::FleteGarantia, self::Tarjeta, self::Otro => true,
            self::PagoGuia, self::RetiroBanco => false,
        };
    }
}
