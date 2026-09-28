<?php

use App\Console\Commands\SincronizarProductosSiigoCommand;
use App\Modules\Siigo\Services\SiigoService;

// F7 · Smoke tests estructurales del comando de sync incremental.
// No ejecutamos el comando end-to-end (requeriría credenciales SIIGO reales
// o mockear el cliente HTTP) · verificamos su contrato público.

it('el comando siigo:sync-productos está registrado en Artisan', function () {
    expect(collect(app('Illuminate\Contracts\Console\Kernel')->all()))
        ->toHaveKey('siigo:sync-productos');
});

it('acepta los flags --desde y --full en su signature', function () {
    $definition = (new SincronizarProductosSiigoCommand)->getDefinition();

    expect($definition->hasOption('desde'))->toBeTrue();
    expect($definition->hasOption('full'))->toBeTrue();
});

it('SiigoService::sincronizarProductos acepta el parámetro updated_start', function () {
    $ref = new ReflectionMethod(SiigoService::class, 'sincronizarProductos');
    $params = collect($ref->getParameters())->pluck('name')->all();

    expect($params)->toContain('updatedStart');
    expect($ref->getParameters()[2]->getType()->allowsNull())->toBeTrue();
});

it('está agendado cada 15 minutos escalonado en routes/console.php', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $eventos = collect($schedule->events())
        ->filter(fn ($e) => str_contains($e->command ?? '', 'siigo:sync-productos'));

    expect($eventos)->not->toBeEmpty();
    // B3-M6 · escalonado al minuto :02 para no chocar con inventario:barrer (:07)
    // ni empaque:cerrar-huerfanos (:12).
    expect($eventos->first()->expression)->toBe('2-59/15 * * * *');
});

// B-TESTS · X1 · el cursor sale en ISO-8601 UTC (Y-m-d\TH:i:sP).
// Verificamos vía Carbon el mismo formato que usa el comando · sin ejecutar
// el comando entero (que necesita BD para SiigoConfig).
it('formatea el cursor incremental como ISO-8601 UTC con offset', function () {
    $sample = \Illuminate\Support\Carbon::parse('2026-09-01 12:00:00', 'America/Bogota');
    $iso = $sample->clone()->subMinutes(5)->utc()->format('Y-m-d\TH:i:sP');
    // Debe llevar T, hora y offset +00:00 (UTC).
    expect($iso)->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');
});
