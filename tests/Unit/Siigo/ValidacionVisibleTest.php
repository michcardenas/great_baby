<?php

// FASE D · Validación visible · smoke tests.

it('la ruta forzar-sync está registrada', function () {
    $rutas = collect(\Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());
    expect($rutas)->toContain('app.catalogo.productos.forzar-sync');
});

it('ProductosController tiene el método forzarSync', function () {
    expect(method_exists(\App\Http\Controllers\App\ProductosController::class, 'forzarSync'))->toBeTrue();
});

it('la ruta verificarProducto de SIIGO sigue existiendo', function () {
    $rutas = collect(\Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());
    expect($rutas)->toContain('app.siigo.verificar.producto');
});

it('ProductoShow.vue incluye el botón "Ver en SIIGO ahora" y el diff', function () {
    $src = file_get_contents(resource_path('js/pages/Catalogo/ProductoShow.vue'));
    expect($src)->toContain('Ver en SIIGO ahora');
    expect($src)->toContain('Forzar re-sync');
    expect($src)->toContain('diffCampos');
});
