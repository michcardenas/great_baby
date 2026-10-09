<?php

/**
 * Marketing tiene que poder pedir mercancía prestada.
 *
 * Hasta el 2026-10-09 no podía: las dos "acciones rápidas" de su panel
 * apuntaban a `/app/inventario/traslados`, pantalla que a ese rol le responde
 * 403 (verificado en el navegador). El panel listaba préstamos que, desde ahí,
 * nadie podía originar.
 *
 * Acá se fija el flujo que sí existe ahora: la solicitud se crea desde el
 * propio módulo, en borrador, y bodega la surte.
 */

use App\Models\User;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Inventario\Enums\EstadoTraslado;
use App\Modules\Inventario\Models\Traslado;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->mkt = User::factory()->create();
    $this->mkt->assignRole(Role::firstOrCreate(['name' => 'Marketing', 'guard_name' => 'web']));

    $this->bodega = InventarioUbicacion::create([
        'codigo' => 'BOG-01', 'nombre' => 'Bodega Bogotá',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);
    $this->almacen = InventarioUbicacion::create([
        'codigo' => 'MKT-BOG', 'nombre' => 'Almacén Marketing',
        'categoria' => CategoriaUbicacion::ReservaProveedor, 'activa' => true,
    ]);
    // Reserva REAL de proveedor: misma categoría que el almacén MKT, distinto
    // prefijo. Es la que se colaba en «mis almacenes».
    $this->reservaProveedor = InventarioUbicacion::create([
        'codigo' => 'RES-PROV-01', 'nombre' => 'Reserva proveedor Andina',
        'categoria' => CategoriaUbicacion::ReservaProveedor, 'activa' => true,
    ]);
});

it('la pantalla de traslados de inventario le sigue negada', function () {
    // Es la razón de que este endpoint exista: si algún día se le abre,
    // conviene que sea una decisión y no un descuido.
    $this->actingAs($this->mkt)->get('/app/inventario/traslados')->assertForbidden();
});

it('crea la solicitud de préstamo en borrador', function () {
    $this->actingAs($this->mkt)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->bodega->id,
            'destino_id' => $this->almacen->id,
            'observaciones' => 'Bodies de verano para las fotos del viernes',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $t = Traslado::firstOrFail();

    expect($t->origen_id)->toBe($this->bodega->id)
        ->and($t->destino_id)->toBe($this->almacen->id)
        ->and($t->motivo)->toBe('prestamo')
        ->and($t->estado)->toBe(EstadoTraslado::Borrador)
        ->and($t->solicitado_por)->toBe($this->mkt->id)
        ->and($t->numero)->toStartWith('TRA-')
        ->and($t->observaciones)->toBe('Bodies de verano para las fotos del viernes');
});

it('crea la devolución en el sentido contrario', function () {
    $this->actingAs($this->mkt)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->almacen->id,
            'destino_id' => $this->bodega->id,
        ])
        ->assertRedirect()->assertSessionHas('success');

    expect(Traslado::firstOrFail()->origen_id)->toBe($this->almacen->id);
});

it('no deja mover mercancía entre dos bodegas de venta', function () {
    $otra = InventarioUbicacion::create([
        'codigo' => 'MED-01', 'nombre' => 'Bodega Medellín',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);

    $this->actingAs($this->mkt)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->bodega->id,
            'destino_id' => $otra->id,
        ])
        ->assertForbidden();

    expect(Traslado::count())->toBe(0);
});

it('no deja pedirle a la reserva de un proveedor', function () {
    // RES-PROV-01 comparte categoría con el almacén MKT. Mientras la consulta
    // usaba `codigo LIKE 'MKT-%' OR categoria = ReservaProveedor`, esta
    // ubicación contaba como almacén propio de Marketing.
    $this->actingAs($this->mkt)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->bodega->id,
            'destino_id' => $this->reservaProveedor->id,
        ])
        ->assertForbidden();

    expect(Traslado::count())->toBe(0);
});

it('rechaza un traslado de una bodega a sí misma', function () {
    $this->actingAs($this->mkt)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->bodega->id,
            'destino_id' => $this->bodega->id,
        ])
        ->assertSessionHasErrors('destino_id');
});

it('el panel ofrece exactamente las bodegas que el endpoint acepta', function () {
    // El fallo que esto previene: dibujar un botón que al pulsarlo da 403.
    $props = $this->actingAs($this->mkt)->get('/app/marketing')
        ->assertOk()
        ->viewData('page')['props'];

    expect(array_column($props['mis_bodegas'], 'codigo'))->toBe(['MKT-BOG'])
        ->and(array_column($props['bodegas_comerciales'], 'codigo'))->toBe(['BOG-01']);

    foreach ($props['bodegas_comerciales'] as $origen) {
        foreach ($props['mis_bodegas'] as $destino) {
            $this->actingAs($this->mkt)
                ->post('/app/marketing/prestamo', [
                    'origen_id' => $origen['id'], 'destino_id' => $destino['id'],
                ])
                ->assertRedirect();
        }
    }
});

it('otro rol no puede crear préstamos de marketing', function () {
    $vendedor = User::factory()->create();
    $vendedor->assignRole(Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']));

    $this->actingAs($vendedor)
        ->post('/app/marketing/prestamo', [
            'origen_id' => $this->bodega->id,
            'destino_id' => $this->almacen->id,
        ])
        ->assertForbidden();

    expect(Traslado::count())->toBe(0);
});
