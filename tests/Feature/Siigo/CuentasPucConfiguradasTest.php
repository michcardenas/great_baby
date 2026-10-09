<?php

/**
 * Las cuentas PUC que el ERP usa para armar asientos tienen que existir de
 * verdad y ser auxiliares.
 *
 * Revisando el 2026-10-09 por qué un asiento de inventario no sube a SIIGO:
 * `siigo.cta_perdida_inventario_default` valía `5299`, que no está en
 * `plan_cuentas`, y `siigo.cta_conciliacion_default` valía `139535`, tampoco.
 * Las otras tres existían pero eran cuentas de encabezado, y SIIGO sólo deja
 * mover en auxiliares. Nada de eso se veía en pantalla: el asiento salía,
 * volvía rechazado y el error moría en el log.
 *
 * Esta pantalla no arregla las cuentas —ese código lo define el contador—,
 * las pone a la vista. Lo que se protege acá es que siga diciendo la verdad.
 */

use App\Models\User;
use App\Support\Reglas;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'Gerencia', 'guard_name' => 'web']));
});

function cuentasPuc($test): array
{
    return $test->actingAs($test->admin)->get('/app/siigo')->assertOk()
        ->viewData('page')['props']['cuentas_puc'];
}

function fila(array $filas, string $clave): array
{
    return collect($filas)->firstWhere('clave', $clave) ?? [];
}

it('marca en rojo una cuenta que no está en el plan', function () {
    Reglas::set('siigo.cta_perdida_inventario_default', '5299');
    DB::table('plan_cuentas')->where('codigo', '5299')->delete();

    $f = fila(cuentasPuc($this), 'siigo.cta_perdida_inventario_default');

    expect($f['estado'])->toBe('rojo')
        ->and($f['codigo'])->toBe('5299')
        ->and($f['detalle'])->toContain('No existe');
});

it('marca en ámbar una cuenta de encabezado', function () {
    DB::table('plan_cuentas')->insertOrIgnore([
        ['codigo' => '5195', 'nombre' => 'Diversos'],
        ['codigo' => '519595', 'nombre' => 'Otros diversos'],
    ]);
    Reglas::set('siigo.cta_gasto_default', '5195');

    $f = fila(cuentasPuc($this), 'siigo.cta_gasto_default');

    expect($f['estado'])->toBe('amber')
        ->and($f['detalle'])->toContain('encabezado');
});

it('da por buena una auxiliar que existe', function () {
    DB::table('plan_cuentas')->insertOrIgnore([
        ['codigo' => '51959501', 'nombre' => 'Gastos varios'],
    ]);
    Reglas::set('siigo.cta_gasto_default', '51959501');

    $f = fila(cuentasPuc($this), 'siigo.cta_gasto_default');

    expect($f['estado'])->toBe('verde')
        ->and($f['detalle'])->toBe('Gastos varios');
});

it('avisa cuando la cuenta quedó sin configurar', function () {
    Reglas::set('siigo.cta_banco_default', '');

    $f = fila(cuentasPuc($this), 'siigo.cta_banco_default');

    expect($f['estado'])->toBe('rojo')
        ->and($f['detalle'])->toContain('Sin configurar');
});

it('revisa las cinco cuentas que el ERP usa para asentar', function () {
    expect(array_column(cuentasPuc($this), 'clave'))->toBe([
        'siigo.cta_gasto_default',
        'siigo.cta_banco_default',
        'siigo.cta_conciliacion_default',
        'siigo.cta_perdida_inventario_default',
        'siigo.cta_sobrante_inventario_default',
    ]);
});
