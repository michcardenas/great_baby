<?php

namespace App\Modules\Cartera\Enums;

/**
 * TO-BE Contabilidad P2 — Clasificación de la diferencia entre monto esperado y monto pagado.
 */
enum ClasificacionDiferencia: string
{
    case DescuentoProntoPago = 'descuento_pronto_pago';
    case FleteAsumidoGb = 'flete_asumido_gb';
    case DescuentoFueraPlazo = 'descuento_fuera_plazo';
    case SaldoPendiente = 'saldo_pendiente';
    case NoIdentificado = 'no_identificado';
    case SobrePago = 'sobre_pago';

    public function label(): string
    {
        return match ($this) {
            self::DescuentoProntoPago => 'Descuento pronto pago',
            self::FleteAsumidoGb => 'Flete asumido por GB',
            self::DescuentoFueraPlazo => 'Descuento fuera de plazo (revisión)',
            self::SaldoPendiente => 'Saldo pendiente',
            self::NoIdentificado => 'No identificado',
            self::SobrePago => 'Sobre-pago (crédito a favor)',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::DescuentoProntoPago => 'success',
            self::FleteAsumidoGb => 'info',
            self::DescuentoFueraPlazo => 'warning',
            self::SaldoPendiente => 'warning',
            self::NoIdentificado => 'danger',
            self::SobrePago => 'info',
        };
    }
}
