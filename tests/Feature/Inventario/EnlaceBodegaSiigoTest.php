<?php

/**
 * Una ubicación se ENLAZA con una bodega que ya existe en SIIGO.
 *
 * Hallado el 2026-10-09 persiguiendo un job caído: el ERP intentaba *crear*
 * la bodega con `POST /v1/warehouses`, y ese catálogo es de **sólo lectura**
 * —el GET responde 200, de ahí salieron las 43 bodegas cacheadas, y el POST
 * responde `404 Resource not found`—. No era la credencial: la prueba es que
 * ninguna ubicación propia del ERP llegó nunca a tener `siigo_id`.
 *
 * El desplegable del formulario sí mandaba el id correcto —el controlador
 * traduce `codigo → id` al armar el payload—, pero el servidor no lo
 * comprobaba: `siigo_id` se validaba como un entero suelto, así que cualquier
 * número entraba y el chip verde de «sincronizado» salía igual, apuntando a
 * una bodega que no existe. Eso es lo que cierran las pruebas de abajo.
 */

use App\Models\User;
use App\Modules\Dropi\Enums\CategoriaUbicacion;
use App\Modules\Dropi\Models\InventarioUbicacion;
use App\Modules\Siigo\Models\SiigoCatalogo;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::factory()->create();
    $this->admin->assignRole(Role::firstOrCreate(['name' => 'Aracely', 'guard_name' => 'web']));

    // Así las guarda el importador: `codigo` es el id de SIIGO, `id` es la
    // fila local y no significa nada allá.
    $this->bodegaSiigo = SiigoCatalogo::create([
        'tipo' => 'warehouses', 'codigo' => '16', 'nombre' => 'Bodega Principal',
    ]);

    $this->ubicacion = InventarioUbicacion::create([
        'codigo' => 'BOG-PPAL', 'nombre' => 'Bodega Principal Bogotá',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);
});

function guardarUbicacion($test, array $extra = [])
{
    return $test->actingAs($test->admin)->postJson('/app/inventario/ubicaciones', array_merge([
        'id' => $test->ubicacion->id,
        'codigo' => $test->ubicacion->codigo,
        'nombre' => $test->ubicacion->nombre,
        'categoria' => CategoriaUbicacion::Venta->value,
        'activa' => true,
    ], $extra));
}

it('enlaza la ubicación con el id que SIIGO conoce', function () {
    guardarUbicacion($this, ['siigo_id' => 16])->assertOk();

    expect($this->ubicacion->refresh()->siigo_id)->toBe('16');
});

it('rechaza el id de la fila local del catálogo', function () {
    // Confusión fácil: `siigo_catalogos.id` es la fila local y no significa
    // nada en SIIGO. El formulario manda el bueno, pero cualquier otra vía
    // —un script, una llamada a mano— mandaba esto sin que nadie chistara.
    guardarUbicacion($this, ['siigo_id' => $this->bodegaSiigo->id])
        ->assertStatus(422)
        ->assertJsonValidationErrors('siigo_id');

    expect($this->ubicacion->refresh()->siigo_id)->toBeNull();
});

it('rechaza una bodega que SIIGO no tiene', function () {
    guardarUbicacion($this, ['siigo_id' => 999999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('siigo_id');
});

it('no confunde una bodega con otro catálogo del mismo id', function () {
    // `siigo_catalogos` guarda juntos warehouses, taxes, price-lists y
    // account-groups. Sin filtrar por tipo, un impuesto con código 16 valdría
    // como bodega.
    SiigoCatalogo::create(['tipo' => 'taxes', 'codigo' => '777', 'nombre' => 'IVA 19%']);

    guardarUbicacion($this, ['siigo_id' => 777])
        ->assertStatus(422)
        ->assertJsonValidationErrors('siigo_id');
});

it('deja la ubicación sin bodega, que es lo normal en un rack', function () {
    // 20 de las 21 ubicaciones sin mapear son racks, niveles y zonas de
    // avería: granularidad interna que no existe en la contabilidad.
    guardarUbicacion($this, ['siigo_id' => null])->assertOk();

    expect($this->ubicacion->refresh()->siigo_id)->toBeNull();
});

it('guardar una ubicación ya no encola ningún push a SIIGO', function () {
    Illuminate\Support\Facades\Queue::fake();

    guardarUbicacion($this, ['siigo_id' => null])->assertOk();

    Illuminate\Support\Facades\Queue::assertNotPushed(
        App\Modules\Siigo\Jobs\PushUbicacionASiigo::class
    );
});

it('el botón "Enviar a SIIGO" explica el camino real en vez de prometer un id', function () {
    $r = $this->actingAs($this->admin)
        ->postJson("/app/inventario/ubicaciones/{$this->ubicacion->id}/enviar-siigo")
        ->assertStatus(422);

    expect($r->json('mensaje'))->toContain('crearla en SIIGO');
});

it('si ya está enlazada, el botón lo dice y no intenta nada', function () {
    $this->ubicacion->forceFill(['siigo_id' => '16'])->save();

    $this->actingAs($this->admin)
        ->postJson("/app/inventario/ubicaciones/{$this->ubicacion->id}/enviar-siigo")
        ->assertOk()
        ->assertJson(['ok' => true]);
});

/*
 * Las bodegas que el importador trajo del catálogo de SIIGO no son
 * ubicaciones que Great Baby opere: existen como destino contable. Son 43
 * contra 21 propias, y como el sandbox es compartido varias son de otras
 * empresas («Tiendanube», «ISLA I0001», «Locacion Jikko MP»).
 *
 * Mezclarlas con las propias tenía un costo concreto: el aviso «N ubicaciones
 * activas sin bodega» —que existe para que nadie tenga racks que ningún
 * responsable puede contar— marcaba 63 cuando los casos reales eran 20. Un
 * aviso que grita sin motivo se vuelve invisible, que es peor que no tenerlo.
 */

function importada(string $codigo): InventarioUbicacion
{
    return InventarioUbicacion::create([
        'codigo' => $codigo, 'nombre' => 'Bodega ajena del sandbox',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);
}

function propsUbicaciones($test): array
{
    return $test->actingAs($test->admin)->get('/app/inventario/ubicaciones')
        ->assertOk()->viewData('page')['props'];
}

it('el aviso de «sin bodega» no cuenta las importadas de SIIGO', function () {
    // La del beforeEach (BOG-PPAL) ya es propia, suelta y sin responsable.
    importada('SIIGO-16');
    importada('SIIGO-19');

    expect(propsUbicaciones($this)['sin_sede'])->toBe(1);
});

it('marca cuáles vinieron del catálogo de SIIGO', function () {
    importada('SIIGO-16');

    $filas = collect(propsUbicaciones($this)['ubicaciones']);

    expect($filas->firstWhere('codigo', 'SIIGO-16')['importada_de_siigo'])->toBeTrue()
        ->and($filas->firstWhere('codigo', 'BOG-PPAL')['importada_de_siigo'])->toBeFalse();
});

it('pone las de Great Baby antes que las importadas', function () {
    // El alfabeto ya lo hace hoy («RES-» < «SIIGO-»), pero es casualidad del
    // prefijo: se rompe con el primer código propio que empiece por T.
    importada('SIIGO-16');
    InventarioUbicacion::create([
        'codigo' => 'TUN-01', 'nombre' => 'Bodega Tunja',
        'categoria' => CategoriaUbicacion::Venta, 'activa' => true,
    ]);

    $codigos = collect(propsUbicaciones($this)['ubicaciones'])->pluck('codigo')->all();

    expect(array_search('TUN-01', $codigos, true))
        ->toBeLessThan(array_search('SIIGO-16', $codigos, true));
});
