<?php

namespace App\Modules\Inventario\Enums;

enum EstadoTomaFisica: string
{
    case Borrador = 'borrador';
    case EnConteo = 'en_conteo';
    case Ajustada = 'ajustada';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnConteo => 'En conteo',
            self::Ajustada => 'Ajustada',
            self::Anulada => 'Anulada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::EnConteo => 'warning',
            self::Ajustada => 'success',
            self::Anulada => 'danger',
        };
    }
}
