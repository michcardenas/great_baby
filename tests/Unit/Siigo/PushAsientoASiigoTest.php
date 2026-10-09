<?php

use DateTimeInterface;
use App\Modules\Siigo\Jobs\PushAsientoASiigo;
use Illuminate\Support\Facades\Queue;

// F11 · Smoke tests del Job de asientos SIIGO.

beforeEach(function () {
    Queue::fake();
});

it('dispatchManual crea job con manual=true', function () {
    PushAsientoASiigo::dispatchManual(movimientoId: 5001);
    Queue::assertPushed(PushAsientoASiigo::class, function ($job) {
        return $job->movimientoId === 5001 && $job->manual === true;
    });
});

it('el job va a la cola siigo y respeta timeout/tries', function () {
    config()->set('siigo.queue', 'siigo');
    $job = new PushAsientoASiigo(movimientoId: 6002);
    expect($job->queue)->toBe('siigo');
    expect($job->timeout)->toBe(60);
    // `tries` ya no gobierna: el worker lo ignora apenas el job define
    // `retryUntil()` (Worker::markJobAsFailedIfWillExceedMaxAttempts). El
    // techo es el reloj, y lo que corta un rechazo real de SIIGO es
    // `maxExceptions`.
    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->retryUntil()->greaterThan(now()->addHours(11)))->toBeTrue()
        ->and($job->maxExceptions)->toBe(5);
});

it('backoff con jitter aleatorio', function () {
    $job = new PushAsientoASiigo(movimientoId: 7003);
    $b = $job->backoff();
    expect($b)->toHaveCount(5);
    expect($b[0])->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(15);
    expect($b[4])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(360);
});

it('middleware RateLimited + WithoutOverlapping', function () {
    $job = new PushAsientoASiigo(movimientoId: 8004);
    $middlewares = collect($job->middleware())->map(fn ($m) => class_basename($m))->all();
    expect($middlewares)->toContain('RateLimited');
    expect($middlewares)->toContain('WithoutOverlapping');
});
