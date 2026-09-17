<?php

namespace Database\Seeders;

use App\Modules\Cartera\Models\MetodoPago;
use Illuminate\Database\Seeder;

/**
 * Métodos de pago base para GREAT BABY (los mencionados en la reunión:
 * efectivo, transferencia, consignación, cruce de cuentas, NEC + billeteras).
 * El cliente ajusta/agrega los suyos. Idempotente por código.
 */
class MetodosPagoSeeder extends Seeder
{
    public function run(): void
    {
        // codigo, nombre, tipo, requiere_referencia, requiere_banco, requiere_comprobante, cuenta_puc, orden
        $metodos = [
            ['efectivo', 'Efectivo', 'efectivo', false, false, false, '110505', 1],
            ['transferencia', 'Transferencia', 'transferencia', true, true, true, '111005', 2],
            ['consignacion', 'Consignación', 'consignacion', true, true, true, '111005', 3],
            ['cruce_cuentas', 'Cruce de cuentas', 'cruce', true, false, false, null, 4],
            ['nec', 'NEC', 'nec', true, false, true, null, 5],
            ['tarjeta', 'Tarjeta', 'tarjeta', true, false, true, '111005', 6],
            ['nequi', 'Nequi', 'billetera', true, false, true, '111005', 7],
            ['daviplata', 'Daviplata', 'billetera', true, false, true, '111005', 8],
        ];

        foreach ($metodos as [$codigo, $nombre, $tipo, $ref, $banco, $comp, $puc, $orden]) {
            MetodoPago::updateOrCreate(
                ['codigo' => $codigo],
                [
                    'nombre' => $nombre,
                    'tipo' => $tipo,
                    'requiere_referencia' => $ref,
                    'requiere_banco' => $banco,
                    'requiere_comprobante' => $comp,
                    'cuenta_puc' => $puc,
                    'activo' => true,
                    'orden' => $orden,
                ],
            );
        }
    }
}
