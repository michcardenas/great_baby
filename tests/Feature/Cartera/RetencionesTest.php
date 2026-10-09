<?php

/**
 * Las retenciones las revisa la DIAN, no nosotros.
 *
 * Retener de menos deja a Great Baby respondiendo por la plata que no giró;
 * retener de más se la quita al proveedor y hay que devolvérsela. Y hay dos
 * reglas que no son obvias y que, si se rompen, nadie nota hasta la auditoría:
 * por debajo de la base mínima NO se retiene, y a un autorretenedor NO se le
 * practica retefuente porque se retiene él mismo (doble retención = sanción).
 *
 * Lo que se verifica acá:
 *   · la tarifa se aplica sobre la base correcta;
 *   · por debajo de la base mínima no se retiene nada;
 *   · a un autorretenedor no se le practica retefuente, pero sí reteica;
 *   · el reteica depende de la ciudad: otra ciudad, no aplica;
 *   · el reteiva se calcula sobre el IVA y sólo a gran contribuyente;
 *   · una regla inactiva no se usa.
 */

use App\Modules\Cartera\Models\RetencionConfig;
use App\Modules\Cartera\Services\CalculadorRetenciones;

beforeEach(function () {
    $this->calc = app(CalculadorRetenciones::class);

    RetencionConfig::create([
        'tipo' => 'retefuente', 'concepto' => 'compras_generales', 'ciudad' => null,
        'base_minima' => 1_000_000, 'tarifa_pct' => 2.5, 'cuenta_puc' => '236540', 'activa' => true,
    ]);
    RetencionConfig::create([
        'tipo' => 'reteica', 'concepto' => 'compras_generales', 'ciudad' => 'Bogotá',
        'base_minima' => 500_000, 'tarifa_pct' => 0.966, 'cuenta_puc' => '236805', 'activa' => true,
    ]);
    RetencionConfig::create([
        'tipo' => 'reteiva', 'concepto' => 'compras_generales', 'ciudad' => null,
        'base_minima' => 0, 'tarifa_pct' => 15, 'cuenta_puc' => '236701', 'activa' => true,
    ]);
});

/** Atajo: devuelve la retención de ese tipo, o null. */
function retencion(array $resultado, string $tipo): ?array
{
    foreach ($resultado as $r) {
        if ($r['tipo'] === $tipo) return $r;
    }
    return null;
}

it('calcula retefuente sobre la base', function () {
    $r = $this->calc->calcular(base: 2_000_000, concepto: 'compras_generales');

    expect(retencion($r, 'retefuente')['valor'])->toBe(50000.0); // 2,5% de 2.000.000
});

it('no retiene por debajo de la base mínima', function () {
    // 900.000 no llega al millón de la base de retefuente, ni al medio millón
    // de reteica: la factura se paga completa.
    $r = $this->calc->calcular(base: 900_000, concepto: 'compras_generales', ciudad: 'Bogotá');

    expect(retencion($r, 'retefuente'))->toBeNull()
        ->and(retencion($r, 'reteica'))->not->toBeNull();

    $r2 = $this->calc->calcular(base: 400_000, concepto: 'compras_generales', ciudad: 'Bogotá');
    expect($r2)->toBeEmpty();
});

it('a un autorretenedor no se le practica retefuente', function () {
    $r = $this->calc->calcular(
        base: 2_000_000, concepto: 'compras_generales', ciudad: 'Bogotá', esAutorretenedor: true
    );

    expect(retencion($r, 'retefuente'))->toBeNull()
        // El reteica es municipal y sí se le practica igual.
        ->and(retencion($r, 'reteica'))->not->toBeNull();
});

it('el reteica sólo aplica en la ciudad configurada', function () {
    $enBogota = $this->calc->calcular(base: 2_000_000, concepto: 'compras_generales', ciudad: 'Bogotá');
    $enCali = $this->calc->calcular(base: 2_000_000, concepto: 'compras_generales', ciudad: 'Cali');
    $sinCiudad = $this->calc->calcular(base: 2_000_000, concepto: 'compras_generales');

    expect(retencion($enBogota, 'reteica')['valor'])->toBe(19320.0) // 0,966% de 2.000.000
        ->and(retencion($enCali, 'reteica'))->toBeNull()
        ->and(retencion($sinCiudad, 'reteica'))->toBeNull();
});

it('el reteiva se calcula sobre el IVA y sólo a gran contribuyente', function () {
    $normal = $this->calc->calcular(
        base: 2_000_000, concepto: 'compras_generales', iva: 380_000, granContribuyente: false
    );
    $grande = $this->calc->calcular(
        base: 2_000_000, concepto: 'compras_generales', iva: 380_000, granContribuyente: true
    );

    expect(retencion($normal, 'reteiva'))->toBeNull()
        ->and(retencion($grande, 'reteiva')['valor'])->toBe(57000.0)  // 15% de 380.000
        ->and(retencion($grande, 'reteiva')['base'])->toBe(380000.0); // la base es el IVA, no el subtotal
});

it('no usa una regla desactivada', function () {
    RetencionConfig::where('tipo', 'retefuente')->update(['activa' => false]);

    $r = $this->calc->calcular(base: 2_000_000, concepto: 'compras_generales');

    expect(retencion($r, 'retefuente'))->toBeNull();
});

it('no retiene por un concepto que no está configurado', function () {
    $r = $this->calc->calcular(base: 5_000_000, concepto: 'servicios_de_transporte', ciudad: 'Bogotá');

    expect($r)->toBeEmpty();
});
