<?php

namespace App\Modules\Compras\Enums;

enum EstadoImportacion: string
{
    case EnTransito = 'en_transito';
    case EnPuerto = 'en_puerto';
    case Nacionalizada = 'nacionalizada';
    case Liquidada = 'liquidada';
    case Cerrada = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::EnTransito => 'En tránsito',
            self::EnPuerto => 'En puerto',
            self::Nacionalizada => 'Nacionalizada',
            self::Liquidada => 'Liquidada',
            self::Cerrada => 'Cerrada',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnTransito => 'info',
            self::EnPuerto => 'warning',
            self::Nacionalizada => 'primary',
            self::Liquidada => 'success',
            self::Cerrada => 'gray',
        };
    }
}
