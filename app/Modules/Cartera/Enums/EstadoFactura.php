<?php

namespace App\Modules\Cartera\Enums;

enum EstadoFactura: string
{
    case Borrador = 'borrador';
    case Pendiente = 'pendiente';
    case Pagada = 'pagada';
    case Abonada = 'abonada';
    case Vencida = 'vencida';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Pendiente => 'Pendiente',
            self::Pagada => 'Pagada',
            self::Abonada => 'Abonada',
            self::Vencida => 'Vencida',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Pendiente => 'warning',
            self::Pagada => 'success',
            self::Abonada => 'info',
            self::Vencida => 'danger',
            self::Anulada => 'gray',
        };
    }
}
