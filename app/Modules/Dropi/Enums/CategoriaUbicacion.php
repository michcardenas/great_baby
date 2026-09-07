<?php

namespace App\Modules\Dropi\Enums;

/**
 * §7 Diseño Dropi — La ubicación del inventario NO es solo dónde está el producto,
 * también define para qué está disponible.
 */
enum CategoriaUbicacion: string
{
    case Venta = 'venta';
    case AveriaReparar = 'averia_reparar';
    case AveriaBaja = 'averia_baja';
    case Garantia = 'garantia';
    case ReservaProveedor = 'reserva_proveedor';

    public function label(): string
    {
        return match ($this) {
            self::Venta => 'Stock de venta',
            self::AveriaReparar => 'Avería — por reparar',
            self::AveriaBaja => 'Avería — baja total',
            self::Garantia => 'Reservado para garantía',
            self::ReservaProveedor => 'Reserva de proveedor',
        };
    }

    public function disponibleParaVenta(): bool
    {
        return match ($this) {
            self::Venta, self::ReservaProveedor => true,
            self::AveriaReparar, self::AveriaBaja, self::Garantia => false,
        };
    }
}
