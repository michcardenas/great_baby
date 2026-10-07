<?php

use Illuminate\Database\Migrations\Migration;

/**
 * `ver.comisiones` pasa a significar «correr y aprobar el cierre mensual de
 * comisiones de todos los vendedores».
 *
 * La sección estaba declarada en la matriz desde el principio con
 * `['Gerente','Contador','Vendedor']`, pero no gateaba ninguna pantalla: era
 * un permiso sin efecto. Al construir el cierre mensual en Vue y hacer que
 * ese permiso lo proteja, el Vendedor —que lo tenía sembrado— habría quedado
 * liquidando las comisiones de la empresa, incluidas las de sus compañeros.
 *
 * La pantalla equivalente en Filament pedía root, Gerente o Contador. Esta
 * migración deja la base igual a eso.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->quitar('Vendedor', 'ver.comisiones');
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se devuelve: darle el cierre de comisiones al Vendedor fue un
        // error de la matriz original, no una decisión.
    }

    private function quitar(string $rol, string $permiso): void
    {
        try {
            $r = \Spatie\Permission\Models\Role::where('name', $rol)->first();
            if ($r && $r->hasPermissionTo($permiso)) {
                $r->revokePermissionTo($permiso);
            }
        } catch (\Throwable) {
            // El permiso aún no existe (instalación nueva sin seeder): el
            // seeder lo creará ya con los roles correctos.
        }
    }
};
