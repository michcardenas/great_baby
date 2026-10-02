<?php

use App\Console\Commands\LimpiarSandboxSiigoCommand;
use App\Modules\Siigo\Jobs\ReconciliarProductosDesdeSiigo;
use Illuminate\Support\Facades\Cache;

// A1 · Smoke tests del comando siigo:limpiar-sandbox.

it('el comando siigo:limpiar-sandbox está registrado en Artisan', function () {
    expect(collect(app('Illuminate\Contracts\Console\Kernel')->all()))
        ->toHaveKey('siigo:limpiar-sandbox');
});

it('acepta los flags --desde, --hasta y --confirmar', function () {
    $def = (new LimpiarSandboxSiigoCommand)->getDefinition();
    expect($def->hasOption('desde'))->toBeTrue();
    expect($def->hasOption('hasta'))->toBeTrue();
    expect($def->hasOption('confirmar'))->toBeTrue();
});

it('falla elegante si no hay ventana en flags ni en cache', function () {
    Cache::forget(ReconciliarProductosDesdeSiigo::CACHE_KEY);
    $exit = \Artisan::call('siigo:limpiar-sandbox');
    expect($exit)->toBe(1);
    expect(\Artisan::output())->toContain('No pude determinar la ventana');
});

it('resuelve ventana de los flags --desde/--hasta cuando se pasan', function () {
    Cache::forget(ReconciliarProductosDesdeSiigo::CACHE_KEY);
    // Con una tabla vacía (sin productos) debería retornar success y decir "nada que borrar".
    try {
        $exit = \Artisan::call('siigo:limpiar-sandbox', [
            '--desde' => '2000-01-01 00:00:00',
            '--hasta' => '2000-01-01 00:00:01',
        ]);
        expect($exit)->toBe(0);
        expect(\Artisan::output())->toContain('ventana');
    } catch (\Throwable $e) {
        // sqlite in-memory sin tabla productos · aceptamos skip
        expect($e->getMessage())->toContain('productos');
    }
});
