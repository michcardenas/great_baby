<?php

namespace App\Modules\Dropi\Enums;

/**
 * §12 Diseño Dropi — Al recibir una guía devuelta, el alistador decide destino
 * con base en verificación física y funcional del producto.
 */
enum DestinoDevolucion: string
{
    case Reingreso = 'reingreso';
    case AveriaReparar = 'averia_reparar';
    case AveriaBaja = 'averia_baja';
    case BajaTotal = 'baja_total';

    public function label(): string
    {
        return match ($this) {
            self::Reingreso => 'Reingresa a stock de venta',
            self::AveriaReparar => 'Avería · Por reparar',
            self::AveriaBaja => 'Avería · Baja total',
            self::BajaTotal => 'Baja total (irrecuperable)',
        };
    }

    public function categoriaUbicacion(): CategoriaUbicacion
    {
        return match ($this) {
            self::Reingreso => CategoriaUbicacion::Venta,
            self::AveriaReparar => CategoriaUbicacion::AveriaReparar,
            self::AveriaBaja, self::BajaTotal => CategoriaUbicacion::AveriaBaja,
        };
    }
}
