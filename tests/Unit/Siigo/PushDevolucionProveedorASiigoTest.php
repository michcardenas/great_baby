<?php

use DateTimeInterface;
use App\Modules\Siigo\Jobs\PushDevolucionProveedorASiigo;
use Illuminate\Support\Facades\Queue;

// COMP-B1 · Smoke tests del Job que envía la NC de compra a SIIGO.
// Mismo patrón que PushRecepcionASiigo (Unit sin BD).

beforeEach(function () {
    Queue::fake();
});

it('kill-switch · push_auto=false deja el flag en false para el handle', function () {
    config()->set('siigo.push_auto', false);
    $job = new PushDevolucionProveedorASiigo(devolucionId: 1);
    expect($job->manual)->toBeFalse();
    expect(config('siigo.push_auto'))->toBeFalse();
});

it('dispatchManual · marca manual=true y bypasea el kill-switch', function () {
    config()->set('siigo.push_auto', false);
    PushDevolucionProveedorASiigo::dispatchManual(id: 7);
    Queue::assertPushed(PushDevolucionProveedorASiigo::class, function ($job) {
        return $job->devolucionId === 7 && $job->manual === true;
    });
});

it('cola correcta (siigo) y timeouts/retries estándar', function () {
    config()->set('siigo.queue', 'siigo');
    $job = new PushDevolucionProveedorASiigo(devolucionId: 42);
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

it('backoff progresivo (10 · 30 · 60 · 120 · 300 segundos)', function () {
    $job = new PushDevolucionProveedorASiigo(devolucionId: 42);
    $b = $job->backoff();
    expect($b)->toHaveCount(5);
    expect($b[0])->toBe(10);
    expect($b[4])->toBe(300);
});

it('middleware() incluye RateLimited y WithoutOverlapping', function () {
    $job = new PushDevolucionProveedorASiigo(devolucionId: 99);
    $middlewares = collect($job->middleware())->map(fn ($m) => class_basename($m))->all();
    expect($middlewares)->toContain('RateLimited');
    expect($middlewares)->toContain('WithoutOverlapping');
});
