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
    // Re-audit DR-β UX (UX-C4) · "mercancía fantasma" — Dropi marca devuelto
    //   pero el paquete NUNCA llegó físicamente a bodega. No genera movimiento
    //   de kardex y dispara alerta en Discrepancias (REU-2).
    case NoLlegoFisicamente = 'no_llego_fisicamente';

    public function label(): string
    {
        return match ($this) {
            self::Reingreso => 'Reingresa a stock de venta',
            self::AveriaReparar => 'Avería · Por reparar',
            self::AveriaBaja => 'Avería · Baja total',
            self::BajaTotal => 'Baja total (irrecuperable)',
            self::NoLlegoFisicamente => 'NO llegó físicamente (mercancía fantasma)',
        };
    }

    public function categoriaUbicacion(): CategoriaUbicacion
    {
        return match ($this) {
            self::Reingreso => CategoriaUbicacion::Venta,
            self::AveriaReparar => CategoriaUbicacion::AveriaReparar,
            self::AveriaBaja, self::BajaTotal => CategoriaUbicacion::AveriaBaja,
            // NoLlegoFisicamente: no aplica ubicación real. El caller debe
            //   evitar reingresar inventario para este destino.
            self::NoLlegoFisicamente => CategoriaUbicacion::AveriaBaja,
        };
    }

    /**
     * ¿Este destino reingresa mercancía al kardex?
     */
    public function reingresaInventario(): bool
    {
        return ! in_array($this, [self::BajaTotal, self::NoLlegoFisicamente], true);
    }
}
