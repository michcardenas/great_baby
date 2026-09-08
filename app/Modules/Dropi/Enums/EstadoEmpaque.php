<?php

namespace App\Modules\Dropi\Enums;

/**
 * Estados de un EmpaqueRegistro.
 * Reemplaza los strings mágicos ('en_curso', 'completado', 'anulado') repartidos por el código.
 */
enum EstadoEmpaque: string
{
    case EnCurso = 'en_curso';
    case Completado = 'completado';
    case Anulado = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::EnCurso => 'En curso',
            self::Completado => 'Completado',
            self::Anulado => 'Anulado',
        };
    }
}
