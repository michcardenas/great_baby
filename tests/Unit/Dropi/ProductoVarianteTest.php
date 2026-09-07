<?php

use App\Modules\Dropi\Models\ProductoVariante;

it('genera codigo de barras segun §9 diseño Dropi', function () {
    // Caso ropa: Ref-Color+Diseño-Talla
    expect(ProductoVariante::generarCodigoBarras('AND2512-79/154', '02', 'LEÓ', '6M'))
        ->toBe('AND2512-79/154-02LEÓ-6M');

    // Sin talla (accesorio)
    expect(ProductoVariante::generarCodigoBarras('BAB4402-11', '01', null, null))
        ->toBe('BAB4402-11-01');

    // Solo referencia (sin variantes)
    expect(ProductoVariante::generarCodigoBarras('SIMPLE-01', null, null, null))
        ->toBe('SIMPLE-01');
});
