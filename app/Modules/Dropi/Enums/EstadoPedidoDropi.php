<?php

namespace App\Modules\Dropi\Enums;

enum EstadoPedidoDropi: string
{
    case Pending = 'pending';
    case PendienteInventario = 'pendiente_inventario';
    case Alistando = 'alistando';
    case Empacado = 'empacado';
    case Despachado = 'despachado';
    case Entregado = 'entregado';
    case DevolucionEnCamino = 'devolucion_en_camino';
    case Devuelto = 'devuelto';
    case Pagado = 'pagado';
    case CanceladoDropi = 'cancelado_dropi';
    case CanceladoGb = 'cancelado_gb';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Nuevo',
            self::PendienteInventario => 'Pendiente por inventario',
            self::Alistando => 'Alistando',
            self::Empacado => 'Empacado',
            self::Despachado => 'Despachado',
            self::Entregado => 'Entregado',
            self::DevolucionEnCamino => 'Devolución en camino',
            self::Devuelto => 'Devuelto',
            self::Pagado => 'Pagado',
            self::CanceladoDropi => 'Cancelado por Dropi',
            self::CanceladoGb => 'Cancelado por GB',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Alistando, self::Empacado => 'warning',
            self::PendienteInventario => 'danger',
            self::Despachado, self::Entregado, self::Pagado => 'success',
            self::DevolucionEnCamino, self::Devuelto => 'gray',
            self::CanceladoDropi, self::CanceladoGb => 'danger',
        };
    }
}
