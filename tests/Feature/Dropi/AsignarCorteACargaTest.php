<?php

use App\Modules\Dropi\Actions\AsignarCorteACarga;
use Carbon\CarbonImmutable;

it('asigna corte 1 antes de las 14:00', function () {
    $corte = AsignarCorteACarga::run(CarbonImmutable::parse('2026-09-04 10:00'));

    expect($corte->fecha->toDateString())->toBe('2026-09-04')
        ->and($corte->numero)->toBe(1);
});

it('asigna corte 2 desde las 14:00', function () {
    $corte = AsignarCorteACarga::run(CarbonImmutable::parse('2026-09-04 14:30'));

    expect($corte->fecha->toDateString())->toBe('2026-09-04')
        ->and($corte->numero)->toBe(2);
});

it('no crea corte duplicado el mismo dia/numero', function () {
    $c1 = AsignarCorteACarga::run(CarbonImmutable::parse('2026-09-04 09:00'));
    $c2 = AsignarCorteACarga::run(CarbonImmutable::parse('2026-09-04 13:59'));

    expect($c1->id)->toBe($c2->id);
});
