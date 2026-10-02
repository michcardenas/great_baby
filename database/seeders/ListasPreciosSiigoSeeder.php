<?php

namespace Database\Seeders;

use App\Modules\Catalogo\Models\ListaPrecios;
use Illuminate\Database\Seeder;

/**
 * FASE H3 · Las 8 listas de precio que muestra el form SIIGO.
 *
 * Nombres EXACTOS (incluyendo mayúsculas/minúsculas/tildes faltantes) como
 * los ve Aracely en SIIGO. Son `updateOrCreate` por `codigo` para no romper
 * las listas genéricas del ERP (Mayorista/Detal/Dropi/Distribuidor).
 */
class ListasPreciosSiigoSeeder extends Seeder
{
    public function run(): void
    {
        $listas = [
            ['codigo' => 'SIIGO_CLI_PREF',  'nombre' => 'Cliente Preferente',       'canal' => 'siigo'],
            ['codigo' => 'SIIGO_AS_SUP',    'nombre' => 'Asistente supervisor',     'canal' => 'siigo'],
            ['codigo' => 'SIIGO_SUP',       'nombre' => 'Supervisor',               'canal' => 'siigo'],
            ['codigo' => 'SIIGO_AS_GER',    'nombre' => 'Asistente Gerente',        'canal' => 'siigo'],
            ['codigo' => 'SIIGO_GER',       'nombre' => 'Gerente',                  'canal' => 'siigo'],
            ['codigo' => 'SIIGO_RETAIL',    'nombre' => 'Retail',                   'canal' => 'siigo'],
            ['codigo' => 'SIIGO_PVP',       'nombre' => 'Precio de venta Publico',  'canal' => 'siigo'],
            ['codigo' => 'SIIGO_14999',     'nombre' => '14.999',                   'canal' => 'siigo'],
        ];

        foreach ($listas as $l) {
            ListaPrecios::updateOrCreate(
                ['codigo' => $l['codigo']],
                ['nombre' => $l['nombre'], 'canal' => $l['canal'], 'activa' => true, 'predeterminada' => false],
            );
        }
    }
}
