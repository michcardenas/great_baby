<?php

/**
 * La ciudad del contacto no viaja a SIIGO como texto: viaja como código DANE.
 *
 * Si el ERP no reconoce lo escrito, emite con la ciudad por defecto (Bogotá) y
 * lo deja sólo en el log, así que un dedazo manda la factura electrónica a
 * otra ciudad sin que nadie se entere. Desde el 2026-10-09 el formulario de
 * contactos avisa en vivo, y ese aviso lo calcula el front con su propia copia
 * de la regla: acá se fija la de PHP para que las dos digan lo mismo. Si una
 * de las dos cambia sin la otra, el formulario muestra ✓ y la factura sale a
 * Bogotá — que es exactamente el fallo que esto previene.
 */

use App\Modules\Siigo\Support\CiudadesDane;

it('traduce la ciudad a su código DANE', function () {
    expect(CiudadesDane::resolver('Medellín'))
        ->toBe(['state_code' => '05', 'city_code' => '05001', 'exacta' => true]);
});

it('aguanta cómo la escribe la gente', function (string $escrito, string $codigo) {
    expect(CiudadesDane::resolver($escrito)['city_code'])->toBe($codigo)
        ->and(CiudadesDane::reconoce($escrito))->toBeTrue();
})->with([
    'sin tilde' => ['Medellin', '05001'],
    'en mayúsculas' => ['ITAGUI', '05360'],
    'con el departamento detrás' => ['Mosquera, Cund.', '25473'],
    'con espacios de sobra' => ['  Santa  Marta ', '47001'],
    'Bogotá a secas' => ['Bogota', '11001'],
    'Bogotá con D.C.' => ['Bogota D.C', '11001'],
    'el nombre viejo' => ['Santafé de Bogotá', '11001'],
    'con diéresis' => ['Itagüí', '05360'],
]);

it('cae en la ciudad por defecto cuando no la conoce, y lo declara', function () {
    $r = CiudadesDane::resolver('Sogamoso');

    expect($r['city_code'])->toBe('11001')
        // `exacta` es lo que el servicio de emisión mira para dejar el warning
        // en el log; si esto dejara de ser false, el fallo pasaría callado.
        ->and($r['exacta'])->toBeFalse()
        ->and(CiudadesDane::reconoce('Sogamoso'))->toBeFalse();
});

it('trata la ciudad vacía como desconocida', function () {
    expect(CiudadesDane::reconoce(null))->toBeFalse()
        ->and(CiudadesDane::reconoce('   '))->toBeFalse()
        ->and(CiudadesDane::resolver(null)['exacta'])->toBeFalse();
});

it('entrega el listado para el formulario, ordenado sin que las tildes estorben', function () {
    $filas = CiudadesDane::listado();

    expect($filas)->not->toBeEmpty();

    // Ibagué iba al final cuando se ordenaba por bytes.
    $nombres = array_column($filas, 'ciudad');
    $sinTildes = array_map(
        fn ($n) => strtr(mb_strtolower($n), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']),
        $nombres
    );
    $ordenado = $sinTildes;
    sort($ordenado);
    expect($sinTildes)->toBe($ordenado);

    // Cada fila trae lo que el formulario necesita para autocompletar.
    foreach ($filas as $f) {
        expect($f)->toHaveKeys(['ciudad', 'departamento', 'city_code'])
            ->and($f['departamento'])->not->toBe('')
            ->and($f['city_code'])->toMatch('/^\d{5}$/');
    }
});

it('toda ciudad del listado se reconoce a sí misma', function () {
    // Protege el caso tonto y letal: ofrecer en el desplegable un nombre que
    // después el resolver no encuentra.
    foreach (CiudadesDane::listado() as $f) {
        expect(CiudadesDane::resolver($f['ciudad'])['city_code'])
            ->toBe($f['city_code'], "«{$f['ciudad']}» se ofrece pero no se resuelve");
    }
});
