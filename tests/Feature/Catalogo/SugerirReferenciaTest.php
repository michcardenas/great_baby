<?php

/**
 * El botón "Generar automáticamente" del SKU tiene que entregar lo que el
 * campo promete.
 *
 * Hasta el 2026-10-09 tomaba el ÚLTIMO producto creado —cualquiera— y le
 * sumaba 1 al número final. Con el catálogo real de Aracely, donde las
 * referencias vienen del Excel del proveedor, el botón que dice "Ej: GB-0001"
 * devolvía cosas como `PRUEBA-REAL-104018`. Y al entrar al bucle de choque
 * el ancho del relleno se recalculaba sobre un int, así que después de un
 * choque proponía `GB-19` en vez de `GB-0019`.
 */

use App\Models\User;
use App\Modules\Dropi\Models\Producto;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(
        Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Gerencia', 'guard_name' => 'web'])
    );
});

function crearProducto(string $referencia): Producto
{
    return Producto::sinKardexAutomatico(fn () => Producto::create([
        'referencia' => $referencia,
        'nombre' => 'Producto '.$referencia,
        'desglose_stock' => false,
    ]));
}

function sugerencia($test): string
{
    return $test->actingAs($test->admin)
        ->getJson('/app/catalogo/productos/sugerir-referencia')
        ->assertOk()
        ->json('referencia');
}

it('arranca en GB-0001 cuando el catálogo está vacío', function () {
    expect(sugerencia($this))->toBe('GB-0001');
});

it('ignora las referencias que no son de la familia GB', function () {
    // Esto es el catálogo real: códigos del proveedor, no correlativos GB.
    crearProducto('PRUEBA-REAL-104017');
    crearProducto('1001');
    crearProducto('ALM-TRANSPORTE');

    expect(sugerencia($this))->toBe('GB-0001');
});

it('sigue el correlativo GB más alto, no el producto más reciente', function () {
    crearProducto('GB-0007');
    crearProducto('PRUEBA-REAL-104017');   // creado DESPUÉS, no debe mandar

    expect(sugerencia($this))->toBe('GB-0008');
});

it('conserva los cuatro dígitos después de saltar un código ocupado', function () {
    crearProducto('GB-0017');
    crearProducto('GB-0018');   // el siguiente natural ya existe

    expect(sugerencia($this))->toBe('GB-0019');
});

it('no reutiliza el código de un producto eliminado', function () {
    $p = crearProducto('GB-0003');
    $p->delete();

    // Si lo reutilizara, el unique de la tabla lo rebotaría al guardar.
    expect(sugerencia($this))->toBe('GB-0004');
});

it('crece más allá de cuatro dígitos sin romperse', function () {
    crearProducto('GB-9999');

    expect(sugerencia($this))->toBe('GB-10000');
});
