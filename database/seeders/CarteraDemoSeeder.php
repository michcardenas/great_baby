<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Modules\Cartera\Actions\RegistrarPago;
use App\Modules\Cartera\Enums\EstadoFactura;
use App\Modules\Cartera\Models\CondicionCredito;
use App\Modules\Cartera\Models\FacturaVenta;
use Illuminate\Database\Seeder;

class CarteraDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Contactos B2B de ejemplo
        $clientesB2B = [
            ['num' => '900123456', 'tipo' => 'NIT', 'nom' => 'Distribuidora Bogotá SAS', 'raz' => 'DISTRIBUIDORA BOGOTA SAS', 'ciu' => 'Bogotá', 'tel' => '3001112233', 'cupo' => 5000000, 'plazo' => 30, 'pp' => 10, 'plazoPP' => 10],
            ['num' => '900234567', 'tipo' => 'NIT', 'nom' => 'Baby Store Medellín', 'raz' => 'BABY STORE MEDELLIN SAS', 'ciu' => 'Medellín', 'tel' => '3012223344', 'cupo' => 3000000, 'plazo' => 45, 'pp' => 5, 'plazoPP' => 15],
            ['num' => '900345678', 'tipo' => 'NIT', 'nom' => 'Comercial Cali', 'raz' => 'COMERCIAL CALI SAS', 'ciu' => 'Cali', 'tel' => '3023334455', 'cupo' => 2000000, 'plazo' => 30, 'pp' => 0, 'plazoPP' => 0],
            ['num' => '900456789', 'tipo' => 'NIT', 'nom' => 'Mundo Bebé Barranquilla', 'raz' => 'MUNDO BEBE BARRANQUILLA SAS', 'ciu' => 'Barranquilla', 'tel' => '3034445566', 'cupo' => 4000000, 'plazo' => 30, 'pp' => 8, 'plazoPP' => 10],
            ['num' => '900567890', 'tipo' => 'NIT', 'nom' => 'Todo Bebé Bucaramanga', 'raz' => 'TODO BEBE BUCARAMANGA SAS', 'ciu' => 'Bucaramanga', 'tel' => '3045556677', 'cupo' => 1500000, 'plazo' => 60, 'pp' => 0, 'plazoPP' => 0],
        ];

        foreach ($clientesB2B as $c) {
            $contacto = Contacto::updateOrCreate(
                ['numero_documento' => $c['num']],
                [
                    'tipo_documento' => $c['tipo'],
                    'nombre_completo' => $c['nom'],
                    'razon_social' => $c['raz'],
                    'ciudad' => $c['ciu'], 'departamento' => 'Colombia',
                    'telefono' => $c['tel'],
                    'email' => strtolower(str_replace(' ', '', $c['nom'])) . '@ejemplo.com',
                    'es_cliente' => true, 'es_cliente_b2b' => true, 'activo' => true,
                    'regimen_iva' => 'responsable',
                ]
            );

            CondicionCredito::updateOrCreate(
                ['contacto_id' => $contacto->id, 'activa' => true],
                [
                    'cupo' => $c['cupo'], 'plazo_dias' => $c['plazo'],
                    'descuento_pronto_pago_pct' => $c['pp'],
                    'plazo_pronto_pago_dias' => $c['plazoPP'],
                    'flete_asumido_gb' => false,
                    'vigente_desde' => now()->subMonths(6)->toDateString(),
                ]
            );
        }

        // Un proveedor y un vendedor Dropi
        Contacto::updateOrCreate(
            ['numero_documento' => '901000111'],
            [
                'tipo_documento' => 'NIT',
                'nombre_completo' => 'Textiles Andinos', 'razon_social' => 'TEXTILES ANDINOS SAS',
                'ciudad' => 'Bogotá', 'telefono' => '3151112222',
                'es_proveedor' => true, 'activo' => true,
            ]
        );
        Contacto::updateOrCreate(
            ['numero_documento' => '1094567890'],
            [
                'tipo_documento' => 'CC',
                'nombre_completo' => 'Vendedor Dropi A', 'razon_social' => null,
                'ciudad' => 'Medellín', 'telefono' => '3201234567',
                'es_vendedor_dropi' => true, 'activo' => true,
            ]
        );

        // Facturas con antigüedades variadas
        $contactos = Contacto::where('es_cliente_b2b', true)->get();
        $numero = 1;
        $muestras = [
            [-5, 350000],   // vence en 5 días (al día)
            [10, 850000],   // 10 días de mora
            [45, 1200000],  // 45 días
            [75, 500000],   // 75 días
            [100, 2100000], // 100 días
            [140, 320000],  // 140 días
        ];

        foreach ($contactos as $ct) {
            foreach ($muestras as [$diasMora, $monto]) {
                if (random_int(1, 3) === 1) continue; // no todos los clientes tienen todas
                $vence = now()->subDays($diasMora);
                $emision = $vence->copy()->subDays($ct->condicionVigente?->plazo_dias ?? 30);

                FacturaVenta::updateOrCreate(
                    ['numero' => 'FV-' . str_pad((string) $numero++, 6, '0', STR_PAD_LEFT)],
                    [
                        'contacto_id' => $ct->id,
                        'fecha_emision' => $emision->toDateString(),
                        'fecha_vencimiento' => $vence->toDateString(),
                        'estado' => $vence->isPast() ? EstadoFactura::Vencida : EstadoFactura::Pendiente,
                        'subtotal' => $monto,
                        'total' => $monto,
                        'saldo' => $monto,
                    ]
                );
            }
        }

        // Algunas facturas ya pagadas parcialmente
        $facturaConAbono = FacturaVenta::where('saldo', '>', 0)->inRandomOrder()->first();
        if ($facturaConAbono) {
            RegistrarPago::run(
                facturaId: $facturaConAbono->id,
                montoRecibido: (float) $facturaConAbono->saldo * 0.6,
                fecha: now()->toDateString(),
                medioPago: 'transferencia',
            );
        }
    }
}
