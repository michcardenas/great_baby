<?php

namespace Database\Seeders;

use App\Modules\Catalogo\Models\Categoria;
use Illuminate\Database\Seeder;

/**
 * C-F7 · Semilla de las 9 categorías reales del cliente Aracely
 * (extraídas del Excel "INVENTARIO DR REPORTE.xlsx" del cliente).
 *
 * Idempotente: firstOrCreate por `codigo`. Corre múltiples veces sin duplicar.
 * Se ejecuta desde:
 *   - `php artisan db:seed --class=CategoriasClienteBebesSeeder`
 *   - Automáticamente al inicio de `inventario:cargar-excel-cliente` para
 *     garantizar que las categorías existen antes de crear productos.
 */
class CategoriasClienteBebesSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['codigo' => 'ALIM',   'nombre' => 'ALIMENTACION'],
            ['codigo' => 'ALMO',   'nombre' => 'ALMOHADA'],
            ['codigo' => 'ARTG',   'nombre' => 'ARTICULOS GRANDES'],
            ['codigo' => 'ASEO',   'nombre' => 'ASEO Y BAÑO'],
            ['codigo' => 'CARG',   'nombre' => 'CARGADORES-ARNES'],
            ['codigo' => 'DIDA',   'nombre' => 'DIDACTICOS'],
            ['codigo' => 'JUGU',   'nombre' => 'JUGUETERIA'],
            ['codigo' => 'MEDI',   'nombre' => 'MEDIAS Y ZAPATOS'],
            ['codigo' => 'PROT',   'nombre' => 'PROTECCION'],
        ];

        foreach ($categorias as $cat) {
            Categoria::firstOrCreate(
                ['codigo' => $cat['codigo']],
                ['nombre' => $cat['nombre'], 'activa' => true],
            );
        }

        $this->command?->info('Categorías del cliente (9): ALIMENTACION, ALMOHADA, ARTICULOS GRANDES, ASEO Y BAÑO, CARGADORES-ARNES, DIDACTICOS, JUGUETERIA, MEDIAS Y ZAPATOS, PROTECCION.');
    }
}
