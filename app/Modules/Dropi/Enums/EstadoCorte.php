<?php

namespace App\Modules\Dropi\Enums;

/**
 * Estados del corte diario Dropi. Sólo dos estados reales:
 *  - Abierto : puede recibir pedidos.
 *  - Cerrado : congelado (hash + manifiesto DIAN + remisiones ARI). No se puede editar.
 *
 * P6 · Los estados intermedios que existían antes (alistando/empacando/despachado)
 * nunca se seteaban en el flujo — código muerto que confundía a los widgets. Eliminados.
 */
enum EstadoCorte: string
{
    case Abierto = 'abierto';
    case Cerrado = 'cerrado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Cerrado => 'Cerrado',
        };
    }
}
