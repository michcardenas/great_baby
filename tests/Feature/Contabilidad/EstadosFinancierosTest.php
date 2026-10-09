<?php

/**
 * Los dos estados financieros que el hub listaba como «no listo».
 *
 * Un balance general que no cuadre, o un estado de resultados que mezcle el
 * costo con los gastos, no sirven para decidir nada: se ven bien y mienten.
 * Las reglas que se protegen acá son las que no se notan a simple vista:
 *
 *   · el signo depende de la naturaleza de la cuenta (activo y gasto son
 *     `debe − haber`; pasivo, patrimonio e ingreso son `haber − debe`);
 *   · el balance ACUMULA desde el primer asiento, no muestra el mes;
 *   · la utilidad del ejercicio no está en ninguna cuenta: hay que calcularla
 *     y sumarla al patrimonio, o la ecuación nunca cuadra;
 *   · la utilidad bruta descuenta el costo pero NO los gastos de operación.
 */

use App\Modules\Cartera\Models\FacturaVenta;
use App\Modules\Cartera\Models\MovimientoContable;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Illuminate\Support\Facades\Queue::fake();
    $this->seed(\Database\Seeders\PermisosSeeder::class);
    $rol = Role::firstOrCreate(['name' => 'Aracely', 'guard_name' => 'web']);
    $this->contadora = \App\Models\User::factory()->create();
    $this->contadora->assignRole($rol);
    $this->actingAs($this->contadora);
});

/** Un movimiento suelto, con la fecha que haga falta. */
function mov(string $cuenta, float $debe, float $haber, string $fecha = null): MovimientoContable
{
    return MovimientoContable::create([
        'fecha' => $fecha ?? now()->toDateString(),
        'cuenta_puc' => $cuenta,
        'debe' => $debe,
        'haber' => $haber,
        'origen_type' => FacturaVenta::class,
        'origen_id' => 1,
        'descripcion' => 'Prueba estados financieros',
    ]);
}

/**
 * Escenario mínimo pero completo: una venta de 1.000.000 + IVA, su costo de
 * 600.000 salido del inventario, y un gasto de arriendo de 150.000 pagado
 * desde el banco.
 */
function escenarioContable(string $fecha = null): void
{
    // Venta: CxC contra ingreso + IVA por pagar.
    mov('1305', 1190000, 0, $fecha);
    mov('4135', 0, 1000000, $fecha);
    mov('2408', 0, 190000, $fecha);
    // Costo de la mercancía vendida: sale del inventario.
    mov('6135', 600000, 0, $fecha);
    mov('1435', 0, 600000, $fecha);
    // Gasto de arriendo pagado del banco.
    mov('5120', 150000, 0, $fecha);
    mov('1110', 0, 150000, $fecha);
}

it('el balance general cuadra la ecuación contable', function () {
    escenarioContable();

    $r = $this->get('/app/contabilidad/balance-general');

    $r->assertOk();
    $props = $r->viewData('page')['props'];

    // Activo = 1305 (1.190.000) − 1435 (600.000) − 1110 (150.000) = 440.000
    expect($props['activo']['total'])->toBe(440000.0)
        // Pasivo = IVA por pagar
        ->and($props['pasivo']['total'])->toBe(190000.0)
        // Resultado = 1.000.000 − 600.000 − 150.000
        ->and($props['resultado_ejercicio'])->toBe(250000.0)
        ->and($props['total_patrimonio'])->toBe(250000.0)
        ->and($props['total_pasivo_patrimonio'])->toBe(440000.0)
        ->and($props['descuadre'])->toBe(0.0)
        ->and($props['cuadra'])->toBeTrue();
});

it('presenta el pasivo en positivo, no en negativo', function () {
    // El IVA por pagar es una cuenta de naturaleza crédito. Si se presentara
    // con la misma resta que el activo saldría en −190.000 y el informe sería
    // ilegible.
    escenarioContable();

    $props = $this->get('/app/contabilidad/balance-general')->viewData('page')['props'];
    $iva = collect($props['pasivo']['cuentas'])->firstWhere('codigo', '2408');

    expect($iva['saldo'])->toBe(190000.0)
        ->and($iva['nombre'])->not->toBe('');
});

it('el balance acumula desde el principio, no sólo el mes en curso', function () {
    escenarioContable(now()->subMonths(6)->toDateString());

    $props = $this->get('/app/contabilidad/balance-general')->viewData('page')['props'];

    expect($props['activo']['total'])->toBe(440000.0)
        ->and($props['cuadra'])->toBeTrue();
});

it('el balance respeta la fecha de corte', function () {
    escenarioContable(now()->subDays(2)->toDateString());
    // Un movimiento posterior al corte no puede aparecer.
    mov('1110', 5000000, 0, now()->toDateString());
    mov('3115', 0, 5000000, now()->toDateString());

    $props = $this->get('/app/contabilidad/balance-general?hasta='.now()->subDay()->toDateString())
        ->viewData('page')['props'];

    expect($props['activo']['total'])->toBe(440000.0)
        ->and($props['patrimonio']['total'])->toBe(0.0);
});

it('avisa cuando el balance no cuadra en vez de maquillarlo', function () {
    // Asiento cojo escrito directo en la tabla, como el que dejaría una
    // migración mal hecha o una importación cruda.
    mov('1305', 1000000, 0);

    $props = $this->get('/app/contabilidad/balance-general')->viewData('page')['props'];

    expect($props['cuadra'])->toBeFalse()
        ->and($props['descuadre'])->toBe(1000000.0);
});

it('el estado de resultados separa el costo de los gastos', function () {
    escenarioContable();

    $props = $this->get('/app/contabilidad/estado-resultados')->viewData('page')['props'];

    expect($props['ingresos']['total'])->toBe(1000000.0)
        ->and($props['costo_ventas']['total'])->toBe(600000.0)
        ->and($props['gastos']['total'])->toBe(150000.0)
        // La bruta descuenta el costo pero NO el arriendo.
        ->and($props['utilidad_bruta'])->toBe(400000.0)
        ->and($props['utilidad_neta'])->toBe(250000.0)
        ->and($props['margen_bruto'])->toBe(40.0)
        ->and($props['margen_neto'])->toBe(25.0);
});

it('el estado de resultados sólo cuenta el periodo pedido', function () {
    escenarioContable(now()->subMonths(3)->toDateString());

    $props = $this->get('/app/contabilidad/estado-resultados?desde='
        .now()->startOfMonth()->toDateString().'&hasta='.now()->toDateString())
        ->viewData('page')['props'];

    expect($props['sin_datos'])->toBeTrue()
        ->and($props['ingresos']['total'])->toBe(0.0);
});

it('no calcula margen cuando no hubo ingresos', function () {
    // Dividir por cero acá dejaría el informe en blanco o con «Infinity».
    mov('5120', 150000, 0);
    mov('1110', 0, 150000);

    $props = $this->get('/app/contabilidad/estado-resultados')->viewData('page')['props'];

    expect($props['margen_bruto'])->toBeNull()
        ->and($props['margen_neto'])->toBeNull()
        ->and($props['utilidad_neta'])->toBe(-150000.0);
});

it('los dos reportes ya no figuran como pendientes en el hub', function () {
    $props = $this->get('/app/contabilidad/reportes')->viewData('page')['props'];
    $porNombre = collect($props['reportes'])->keyBy('nombre');

    expect($porNombre['Balance general']['listo'])->toBeTrue()
        ->and($porNombre['Balance general']['href'])->toBe('/app/contabilidad/balance-general')
        ->and($porNombre['Estado de resultados']['listo'])->toBeTrue()
        ->and($porNombre['Estado de resultados']['href'])->toBe('/app/contabilidad/estado-resultados');
});
