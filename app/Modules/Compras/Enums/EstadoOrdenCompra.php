<?php

namespace App\Modules\Compras\Enums;

enum EstadoOrdenCompra: string
{
    case Borrador = 'borrador';
    case Enviada = 'enviada';
    case Aprobada = 'aprobada';
    case Parcial = 'parcial';
    case Recibida = 'recibida';
    case Cerrada = 'cerrada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Enviada => 'Enviada al proveedor',
            self::Aprobada => 'Aprobada',
            self::Parcial => 'Parcialmente recibida',
            self::Recibida => 'Recibida completa',
            self::Cerrada => 'Cerrada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Enviada, self::Aprobada => 'info',
            self::Parcial => 'warning',
            self::Recibida, self::Cerrada => 'success',
            self::Anulada => 'danger',
        };
    }
}
