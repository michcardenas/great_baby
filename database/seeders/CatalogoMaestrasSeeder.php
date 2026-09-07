<?php

namespace Database\Seeders;

use App\Modules\Catalogo\Models\Categoria;
use App\Modules\Catalogo\Models\Coleccion;
use App\Modules\Catalogo\Models\Color;
use App\Modules\Catalogo\Models\Diseno;
use App\Modules\Catalogo\Models\Impuesto;
use App\Modules\Catalogo\Models\ListaPrecios;
use App\Modules\Catalogo\Models\Marca;
use App\Modules\Catalogo\Models\Talla;
use App\Modules\Catalogo\Models\UnidadMedida;
use Illuminate\Database\Seeder;

class CatalogoMaestrasSeeder extends Seeder
{
    public function run(): void
    {
        // Marcas
        foreach ([
            ['AND', 'Andina'], ['KID', 'Kids'], ['BST', 'Baby Star'], ['GBH', 'GB House'],
        ] as [$c, $n]) Marca::updateOrCreate(['codigo' => $c], ['nombre' => $n]);

        // Categorías con jerarquía
        $ropa = Categoria::updateOrCreate(['codigo' => 'ROPA'], ['nombre' => 'Ropa', 'cuenta_puc_ingreso' => '4135']);
        Categoria::updateOrCreate(['codigo' => 'ROPA-BODYS'], ['nombre' => 'Bodys', 'padre_id' => $ropa->id, 'cuenta_puc_ingreso' => '4135']);
        Categoria::updateOrCreate(['codigo' => 'ROPA-SETS'], ['nombre' => 'Sets', 'padre_id' => $ropa->id, 'cuenta_puc_ingreso' => '4135']);
        $acc = Categoria::updateOrCreate(['codigo' => 'ACC'], ['nombre' => 'Accesorios', 'cuenta_puc_ingreso' => '4135']);
        Categoria::updateOrCreate(['codigo' => 'ACC-CHUPOS'], ['nombre' => 'Chupos', 'padre_id' => $acc->id]);
        Categoria::updateOrCreate(['codigo' => 'ACC-TETEROS'], ['nombre' => 'Teteros', 'padre_id' => $acc->id]);

        // Colecciones
        Coleccion::updateOrCreate(['nombre' => 'Otoño 2026'], ['fecha_lanzamiento' => now()->subMonths(2)]);
        Coleccion::updateOrCreate(['nombre' => 'Verano 2026'], ['fecha_lanzamiento' => now()->subMonths(6)]);

        // Colores estándar
        foreach ([
            ['01', 'Blanco', '#ffffff'], ['02', 'Azul', '#1e40af'], ['03', 'Verde', '#16a34a'],
            ['04', 'Amarillo', '#facc15'], ['05', 'Rosa', '#ec4899'], ['06', 'Rojo', '#dc2626'],
            ['07', 'Negro', '#0f0f0f'], ['08', 'Gris', '#6b7280'], ['09', 'Naranja', '#f97316'],
        ] as [$c, $n, $h]) Color::updateOrCreate(['codigo' => $c], ['nombre' => $n, 'hex' => $h]);

        // Diseños
        foreach ([
            ['LEÓ', 'León'], ['FLR', 'Flores'], ['LST', 'Listas'], ['EST', 'Estrellas'],
            ['LUN', 'Lunas'], ['CAR', 'Carritos'], ['OSO', 'Osito'],
        ] as [$c, $n]) Diseno::updateOrCreate(['codigo' => $c], ['nombre' => $n]);

        // Tallas
        foreach ([
            ['6M', '6 meses', 1], ['9M', '9 meses', 2], ['12M', '12 meses', 3],
            ['18M', '18 meses', 4], ['24M', '24 meses', 5], ['UNI', 'Única', 99],
        ] as [$c, $n, $o]) Talla::updateOrCreate(['codigo' => $c], ['nombre' => $n, 'orden' => $o]);

        // Unidades
        foreach ([
            ['UND', 'Unidad'], ['PACK', 'Paquete'], ['DOC', 'Docena'], ['CAJ', 'Caja'],
        ] as [$c, $n]) UnidadMedida::updateOrCreate(['codigo' => $c], ['nombre' => $n]);

        // Impuestos
        Impuesto::updateOrCreate(['codigo' => 'IVA-19'], ['nombre' => 'IVA 19%', 'tipo' => 'iva', 'porcentaje' => 19, 'cuenta_puc' => '2408']);
        Impuesto::updateOrCreate(['codigo' => 'IVA-5'], ['nombre' => 'IVA 5%', 'tipo' => 'iva', 'porcentaje' => 5, 'cuenta_puc' => '2408']);
        Impuesto::updateOrCreate(['codigo' => 'IVA-EXC'], ['nombre' => 'Excluido de IVA', 'tipo' => 'iva', 'porcentaje' => 0]);
        Impuesto::updateOrCreate(['codigo' => 'RTFTE-2.5'], ['nombre' => 'Ret. fuente 2.5%', 'tipo' => 'retencion_fuente', 'porcentaje' => 2.5, 'cuenta_puc' => '2365']);
        Impuesto::updateOrCreate(['codigo' => 'RTICA-6'], ['nombre' => 'ICA 6 x mil', 'tipo' => 'retencion_ica', 'porcentaje' => 0.6, 'cuenta_puc' => '2368']);

        // Listas de precios
        ListaPrecios::updateOrCreate(['codigo' => 'MAY'], ['nombre' => 'Mayorista', 'canal' => 'mayorista', 'predeterminada' => true]);
        ListaPrecios::updateOrCreate(['codigo' => 'DET'], ['nombre' => 'Detal', 'canal' => 'detal']);
        ListaPrecios::updateOrCreate(['codigo' => 'DROPI'], ['nombre' => 'Dropi', 'canal' => 'dropi']);
        ListaPrecios::updateOrCreate(['codigo' => 'DIST'], ['nombre' => 'Distribuidor', 'canal' => 'distribuidor']);
    }
}
