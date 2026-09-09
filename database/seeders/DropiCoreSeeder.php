<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Enums\EstadoCorte;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiCorte;
use App\Modules\Dropi\Models\DropiPedido;
use App\Modules\Dropi\Models\DropiPedidoItem;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DropiCoreSeeder extends Seeder
{
    public function run(): void
    {
        // --- Roles §24 diseño Dropi ---
        foreach (['Aracely', 'Alistador', 'ServicioCliente', 'Gerencia'] as $r) {
            Role::firstOrCreate(['name' => $r, 'guard_name' => 'web']);
        }

        // --- Admin user ---
        $admin = User::updateOrCreate(
            ['email' => 'admin@greatbaby.co'],
            ['name' => 'Aracely (demo)', 'password' => Hash::make('greatbaby2026')]
        );
        $admin->syncRoles(['Aracely']);

        // --- Ubicaciones §7 (5 categorías) ---
        $ubicaciones = [
            ['codigo' => 'R-A-01', 'nombre' => 'Rack A · Nivel 01', 'categoria' => CategoriaUbicacion::Venta],
            ['codigo' => 'R-A-02', 'nombre' => 'Rack A · Nivel 02', 'categoria' => CategoriaUbicacion::Venta],
            ['codigo' => 'R-B-01', 'nombre' => 'Rack B · Nivel 01', 'categoria' => CategoriaUbicacion::Venta],
            ['codigo' => 'AVR-01', 'nombre' => 'Avería · Por reparar', 'categoria' => CategoriaUbicacion::AveriaReparar],
            ['codigo' => 'AVR-02', 'nombre' => 'Avería · Baja total', 'categoria' => CategoriaUbicacion::AveriaBaja],
            ['codigo' => 'GAR-01', 'nombre' => 'Reservado garantías', 'categoria' => CategoriaUbicacion::Garantia],
            ['codigo' => 'RES-PROV-01', 'nombre' => 'Reserva proveedor Andina', 'categoria' => CategoriaUbicacion::ReservaProveedor],
        ];

        foreach ($ubicaciones as $u) {
            InventarioUbicacion::updateOrCreate(['codigo' => $u['codigo']], $u);
        }

        // --- Productos y variantes (ejemplos reales del diseño §9) ---
        $productos = [
            [
                'referencia' => 'AND2512-79/154', 'nombre' => 'Body león manga larga',
                'categoria' => 'ropa', 'precio_proveedor' => 12500, 'requiere_talla' => true,
                'variantes' => [
                    ['color' => '02', 'color_n' => 'azul', 'diseno' => 'LEÓ', 'diseno_n' => 'león', 'talla' => '6M'],
                    ['color' => '02', 'color_n' => 'azul', 'diseno' => 'LEÓ', 'diseno_n' => 'león', 'talla' => '12M'],
                    ['color' => '05', 'color_n' => 'rosa', 'diseno' => 'LEÓ', 'diseno_n' => 'león', 'talla' => '6M'],
                ],
            ],
            [
                'referencia' => 'AND3011-88/201', 'nombre' => 'Set bodys x5 · pack niña',
                'categoria' => 'ropa', 'precio_proveedor' => 42000, 'requiere_talla' => true, 'es_set' => true,
                'variantes' => [
                    ['color' => '05', 'color_n' => 'rosa', 'diseno' => 'FLR', 'diseno_n' => 'flores', 'talla' => '9M'],
                ],
            ],
            [
                'referencia' => 'BAB4402-11', 'nombre' => 'Chupo pico anatómico',
                'categoria' => 'accesorio', 'precio_proveedor' => 8900, 'requiere_talla' => false,
                'variantes' => [
                    ['color' => '01', 'color_n' => 'blanco', 'diseno' => null, 'diseno_n' => null, 'talla' => null],
                ],
            ],
        ];

        foreach ($productos as $data) {
            $variantesData = $data['variantes'];
            unset($data['variantes']);
            $prod = Producto::updateOrCreate(['referencia' => $data['referencia']], $data);

            foreach ($variantesData as $v) {
                $codigo = ProductoVariante::generarCodigoBarras(
                    $prod->referencia, $v['color'], $v['diseno'], $v['talla']
                );
                ProductoVariante::updateOrCreate(
                    ['codigo_barras' => $codigo],
                    [
                        'producto_id' => $prod->id,
                        'color_codigo' => $v['color'], 'color_nombre' => $v['color_n'],
                        'diseno_codigo' => $v['diseno'], 'diseno_nombre' => $v['diseno_n'],
                        'talla' => $v['talla'],
                    ]
                );
            }
        }

        // --- Corte 1 de hoy, con 5 pedidos ejemplo ---
        $corte = DropiCorte::firstOrCreate(
            ['fecha' => now()->toDateString(), 'numero' => 1],
            ['estado' => EstadoCorte::Abierto, 'pedidos_totales' => 0]
        );

        $variante = ProductoVariante::where('codigo_barras', 'AND2512-79/154-02LEÓ-6M')->first();

        $ejemplos = [
            ['guia' => 'GUI-000001', 'cliente' => 'María Pérez', 'ciudad' => 'Bogotá', 'estado' => EstadoPedidoDropi::Pending, 'monto' => 12500],
            ['guia' => 'GUI-000002', 'cliente' => 'Andrea López', 'ciudad' => 'Medellín', 'estado' => EstadoPedidoDropi::Alistando, 'monto' => 25000],
            ['guia' => 'GUI-000003', 'cliente' => 'Carolina Ruiz', 'ciudad' => 'Cali', 'estado' => EstadoPedidoDropi::PendienteInventario, 'monto' => 12500],
            ['guia' => 'GUI-000004', 'cliente' => 'Juan Rodríguez', 'ciudad' => 'Barranquilla', 'estado' => EstadoPedidoDropi::Despachado, 'monto' => 42000],
            ['guia' => 'GUI-000005', 'cliente' => 'Sara Gómez', 'ciudad' => 'Bucaramanga', 'estado' => EstadoPedidoDropi::Pagado, 'monto' => 8900],
        ];

        foreach ($ejemplos as $i => $e) {
            $pedido = DropiPedido::updateOrCreate(
                ['guia' => $e['guia']],
                [
                    'corte_id' => $corte->id,
                    'dropi_orden_id' => 'DRP-' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT),
                    'transportadora' => 'Servientrega',
                    'tienda' => 'Baby Store Demo',
                    'vendedor_nombre' => 'Vendedor Dropi Demo',
                    'requiere_factura_b2b' => false,
                    'cliente_nombre' => $e['cliente'],
                    'cliente_telefono' => '3001234567',
                    'cliente_direccion' => 'Calle Demo #10-20',
                    'cliente_ciudad' => $e['ciudad'],
                    'estado' => $e['estado'],
                    'monto_esperado_proveedor' => $e['monto'],
                    'monto_cliente_final' => $e['monto'] * 1.6,
                    'ganancia_vendedor' => $e['monto'] * 0.4,
                    'flete_transportadora' => 8500,
                    'despachado_at' => in_array($e['estado'], [EstadoPedidoDropi::Despachado, EstadoPedidoDropi::Pagado]) ? now()->subDays(2) : null,
                    'pagado_at' => $e['estado'] === EstadoPedidoDropi::Pagado ? now()->subDay() : null,
                ]
            );

            DropiPedidoItem::updateOrCreate(
                ['pedido_id' => $pedido->id, 'sku_dropi' => $variante->codigo_barras],
                [
                    'variante_id' => $variante->id,
                    'cantidad' => 1,
                    'precio_proveedor_unit' => $e['monto'],
                    'despachado' => in_array($e['estado'], [EstadoPedidoDropi::Despachado, EstadoPedidoDropi::Pagado]),
                ]
            );
        }

        $corte->update([
            'pedidos_totales' => 5,
            'pedidos_pendientes_inv' => 1,
            'pedidos_despachados' => 2,
        ]);
    }
}
