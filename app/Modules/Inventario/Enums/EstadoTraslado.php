<?php

namespace App\Modules\Inventario\Enums;

enum EstadoTraslado: string
{
    case Borrador = 'borrador';
    case EnTransito = 'en_transito';
    case Recibido = 'recibido';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::EnTransito => 'En tránsito',
            self::Recibido => 'Recibido',
            self::Anulado => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::EnTransito => 'warning',
            self::Recibido => 'success',
            self::Anulado => 'danger',
        };
    }
}
