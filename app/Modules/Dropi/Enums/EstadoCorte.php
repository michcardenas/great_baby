<?php

namespace App\Modules\Dropi\Enums;

enum EstadoCorte: string
{
    case Abierto = 'abierto';
    case Alistando = 'alistando';
    case Empacando = 'empacando';
    case Despachado = 'despachado';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Alistando => 'Alistando',
            self::Empacando => 'Empacando',
            self::Despachado => 'Despachado',
            self::Cerrado => 'Cerrado',
        };
    }
}
