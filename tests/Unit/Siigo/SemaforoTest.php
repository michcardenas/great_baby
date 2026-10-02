<?php

// FASE E · Semáforo SIIGO global.

it('la ruta /app/siigo/semaforo está registrada', function () {
    $rutas = collect(\Route::getRoutes()->getRoutes())->map(fn ($r) => $r->getName());
    expect($rutas)->toContain('app.siigo.semaforo');
});

it('SiigoController tiene el método semaforo', function () {
    expect(method_exists(\App\Http\Controllers\App\SiigoController::class, 'semaforo'))->toBeTrue();
});

it('SemaforoSiigo.vue existe y usa polling 30s', function () {
    $path = resource_path('js/Components/SemaforoSiigo.vue');
    expect(file_exists($path))->toBeTrue();
    $src = file_get_contents($path);
    expect($src)->toContain('/app/siigo/semaforo');
    expect($src)->toContain('setInterval(cargar, 30000)');
});

it('AppLayout.vue incluye el componente SemaforoSiigo', function () {
    $src = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
    expect($src)->toContain('SemaforoSiigo');
});
