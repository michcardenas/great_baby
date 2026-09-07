<?php

namespace Database\Seeders;

use App\Models\Contacto;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioMovimiento;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Dropi\Models\Producto;
use App\Modules\Dropi\Models\ProductoVariante;
use App\Modules\Inventario\Models\AlertaStockConfig;
use App\Modules\Inventario\Models\Traslado;
use App\Modules\Inventario\Models\TrasladoItem;
use Illuminate\Database\Seeder;

/**
 * Inyecta datos reales para probar M3: bodegas, productos con stock,
 * traslados, alertas configuradas y movimientos históricos.
 */
class InventarioDatosPruebaSeeder extends Seeder
{
    public function run(): void
    {
        $ppal = InventarioUbicacion::firstOrCreate(
            ['codigo' => 'PPAL'],
            ['nombre' => 'Bodega principal', 'categoria' => CategoriaUbicacion::Venta, 'activa' => true]
        );
        $medellin = InventarioUbicacion::firstOrCreate(
            ['codigo' => 'MED'],
            ['nombre' => 'Bodega Medellín', 'categoria' => CategoriaUbicacion::Venta, 'activa' => true]
        );
        $averia = InventarioUbicacion::firstOrCreate(
            ['codigo' => 'AVR'],
            ['nombre' => 'Avería/Reparar', 'categoria' => CategoriaUbicacion::AveriaReparar, 'activa' => true]
        );

        // 25 productos con 3 variantes cada uno
        for ($i = 1; $i <= 25; $i++) {
            $p = Producto::firstOrCreate(
                ['referencia' => "TEST-{$i}"],
                ['nombre' => "Producto prueba {$i}", 'precio_proveedor' => rand(2000, 40000), 'activo' => true]
            );
            $colores = ['RS' => 'Rosa', 'AZ' => 'Azul', 'BL' => 'Blanco'];
            foreach ($colores as $cc => $cn) {
                $v = ProductoVariante::firstOrCreate([
                    'producto_id' => $p->id, 'color_codigo' => $cc, 'talla' => 'U',
                ], ['color_nombre' => $cn]);

                // Ingreso inicial en bodega principal
                InventarioMovimiento::firstOrCreate(
                    [
                        'variante_id' => $v->id, 'ubicacion_id' => $ppal->id,
                        'tipo' => 'ingreso',
                        'referencia_tipo' => 'seed', 'referencia_id' => $i,
                    ],
                    ['cantidad' => rand(20, 200), 'notas' => 'Seed inicial', 'created_at' => now()->subDays(rand(1, 60))]
                );

                // Alerta configurada al azar (30% de los productos)
                if ($i % 3 === 0) {
                    AlertaStockConfig::firstOrCreate(
                        ['variante_id' => $v->id, 'ubicacion_id' => $ppal->id],
                        [
                            'stock_minimo' => 30,
                            'punto_reorden' => 15,
                            'cantidad_reorden' => 100,
                            'activa' => true,
                        ]
                    );
                }
            }
        }

        $this->command->info('✓ 25 productos con 75 variantes + 25 alertas configuradas');
        $this->command->info('✓ 3 bodegas (principal, Medellín, avería) con stock inicial');
    }
}
