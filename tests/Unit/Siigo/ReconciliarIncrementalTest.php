<?php

use App\Console\Commands\ReconciliarProductosSiigoCommand;
use App\Modules\Siigo\Jobs\ReconciliarProductosDesdeSiigo;

// B2-B4 · Smoke tests del modo incremental de reconciliación.

it('el comando siigo:reconciliar está registrado', function () {
    expect(collect(app('Illuminate\Contracts\Console\Kernel')->all()))
        ->toHaveKey('siigo:reconciliar');
});

it('acepta --full, --desde, --confirmar, --async', function () {
    $def = (new ReconciliarProductosSiigoCommand)->getDefinition();
    expect($def->hasOption('full'))->toBeTrue();
    expect($def->hasOption('desde'))->toBeTrue();
    expect($def->hasOption('confirmar'))->toBeTrue();
    expect($def->hasOption('async'))->toBeTrue();
});

it('--full sin --confirmar es rechazado', function () {
    $exit = \Artisan::call('siigo:reconciliar', ['--full' => true]);
    expect($exit)->toBe(1);
    expect(\Artisan::output())->toContain('requiere --confirmar');
});

it('el Job acepta constructor con full y updatedStart', function () {
    $job = new ReconciliarProductosDesdeSiigo(full: false, updatedStart: '2026-01-01');
    expect($job->full)->toBeFalse();
    expect($job->updatedStart)->toBe('2026-01-01');

    $jobFull = new ReconciliarProductosDesdeSiigo(full: true);
    expect($jobFull->full)->toBeTrue();
    expect($jobFull->updatedStart)->toBeNull();
});

it('la cache_key del Job es la esperada', function () {
    expect(ReconciliarProductosDesdeSiigo::CACHE_KEY)->toBe('siigo:reconciliar:estado');
});
