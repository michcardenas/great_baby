<?php

use Illuminate\Routing\Route;

// FASE C · CRUD robusto · smoke de rutas y controller.

it('rutas de clonar, bulk-edit, papelera y restaurar están registradas', function () {
    $rutas = collect(\Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());
    expect($rutas)->toContain('app.catalogo.productos.clonar');
    expect($rutas)->toContain('app.catalogo.productos.bulk-edit');
    expect($rutas)->toContain('app.catalogo.productos.papelera');
    expect($rutas)->toContain('app.catalogo.productos.restaurar');
});

it('ProductosController tiene los 4 métodos públicos nuevos (clonar/bulkEdit/papelera/restaurar)', function () {
    $metodos = get_class_methods(\App\Http\Controllers\App\ProductosController::class);
    foreach (['clonar', 'bulkEdit', 'papelera', 'restaurar'] as $m) {
        expect(in_array($m, $metodos, true))->toBeTrue("Falta método público {$m}");
    }
    // historialProducto es private · verificamos vía Reflection.
    $r = new ReflectionClass(\App\Http\Controllers\App\ProductosController::class);
    expect($r->hasMethod('historialProducto'))->toBeTrue();
});

it('ProductosPapelera.vue existe', function () {
    expect(file_exists(resource_path('js/pages/Catalogo/ProductosPapelera.vue')))->toBeTrue();
});

it('ProductoShow.vue declara prop historial', function () {
    $src = file_get_contents(resource_path('js/pages/Catalogo/ProductoShow.vue'));
    expect($src)->toContain('historial:');
    expect($src)->toContain('Historial de sync SIIGO');
});
