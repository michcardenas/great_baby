<?php

namespace Database\Seeders;

use App\Auth\Permisos;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Convierte la matriz de autorización que vivía en código (`Permisos::MATRIZ`)
 * en permisos reales de la base, para que el admin pueda crear roles y repartir
 * accesos sin que un programador toque nada.
 *
 * Es idempotente y NO cambia el comportamiento: cada rol queda con exactamente
 * los mismos accesos que tenía escritos en la matriz. A partir de acá, lo que
 * manda es lo que se marque en la pantalla de Roles.
 */
class PermisosSeeder extends Seeder
{
    public function run(): void
    {
        $guard = config('auth.defaults.guard', 'web');

        // 1 · Un permiso por sección. Anotamos los que nacen en esta corrida:
        //     una sección agregada después del primer sembrado tiene que llegar
        //     igual a sus roles, aunque esos roles ya estén configurados.
        $nuevos = [];
        foreach (array_keys(Permisos::MATRIZ) as $seccion) {
            $nombre = Permisos::permiso($seccion);
            if (! Permission::where('name', $nombre)->where('guard_name', $guard)->exists()) {
                Permission::create(['name' => $nombre, 'guard_name' => $guard]);
                $nuevos[$nombre] = $seccion;
            }
        }

        // 1b · Los permisos recién creados se reparten según la matriz, sin
        //      tocar nada de lo que el admin ya haya configurado a mano.
        foreach ($nuevos as $nombre => $seccion) {
            foreach (Role::all() as $rol) {
                $leCorresponde = in_array($rol->name, Permisos::MATRIZ[$seccion], true)
                    || in_array($rol->name, ['Aracely', 'Gerencia'], true);
                if ($leCorresponde) {
                    $rol->givePermissionTo($nombre);
                }
            }
        }

        // 2 · Cada rol recibe los permisos que la matriz le daba.
        //     Se usa syncPermissions sólo en el primer sembrado de ese rol para
        //     no pisar lo que el admin haya configurado después a mano.
        foreach (Role::all() as $rol) {
            if ($rol->permissions()->exists()) {
                continue;
            }

            $suyos = [];
            foreach (Permisos::MATRIZ as $seccion => $roles) {
                if (in_array($rol->name, $roles, true)) {
                    $suyos[] = Permisos::permiso($seccion);
                }
            }

            // Root ve todo: su acceso no depende de las casillas, pero le
            // dejamos los permisos marcados para que la pantalla no muestre
            // a la dueña del sistema con todo en blanco.
            //
            // OJO: "protegido" (no se puede borrar) no es lo mismo que "root"
            // (ve todo). AdminBodega es protegido porque sin él la bodega se
            // detiene, pero sigue viendo sólo lo suyo.
            if (in_array($rol->name, ['Aracely', 'Gerencia'], true)) {
                $suyos = Permission::where('name', 'like', 'ver.%')->pluck('name')->all();
            }

            $rol->syncPermissions($suyos);
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $this->command?->info(
            'Permisos sembrados: '.Permission::where('name', 'like', 'ver.%')->count().
            ' · roles configurados: '.Role::count()
        );
    }
}
