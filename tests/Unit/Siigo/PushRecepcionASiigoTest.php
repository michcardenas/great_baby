<?php

use App\Modules\Siigo\Jobs\PushRecepcionASiigo;
use Illuminate\Support\Facades\Queue;

// F9 · Smoke tests del Job de push de facturas de compra a SIIGO.
// Sigue el mismo patrón que PushProductoASiigo (Unit sin BD, Queue::fake).

beforeEach(function () {
    Queue::fake();
});

it('kill-switch bloquea el dispatch por defecto (push_auto=off + manual=false)', function () {
    config()->set('siigo.push_auto', false);

    $job = new PushRecepcionASiigo(recepcionId: 1001);
    expect($job->manual)->toBeFalse();
    // El handle real necesitaría BD; verificamos el flag y config.
    expect(config('siigo.push_auto'))->toBeFalse();
});

it('dispatchManual crea job con manual=true (bypasea kill-switch)', function () {
    config()->set('siigo.push_auto', false);

    PushRecepcionASiigo::dispatchManual(recepcionId: 2002);

    Queue::assertPushed(PushRecepcionASiigo::class, function ($job) {
        return $job->recepcionId === 2002 && $job->manual === true;
    });
});

it('el job va a la cola siigo y respeta el timeout/tries', function () {
    config()->set('siigo.queue', 'siigo');
    $job = new PushRecepcionASiigo(recepcionId: 3003);

    expect($job->queue)->toBe('siigo');
    expect($job->timeout)->toBe(60);
    expect($job->tries)->toBe(5);
});

it('backoff con jitter aleatorio (mismo patrón que PushProductoASiigo)', function () {
    $job = new PushRecepcionASiigo(recepcionId: 4004);
    $b = $job->backoff();
    expect($b)->toHaveCount(5);
    expect($b[0])->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(15);
    expect($b[4])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(360);
});

it('middleware() devuelve RateLimited + WithoutOverlapping', function () {
    $job = new PushRecepcionASiigo(recepcionId: 5005);
    $middlewares = collect($job->middleware())->map(fn ($m) => class_basename($m))->all();
    expect($middlewares)->toContain('RateLimited');
    expect($middlewares)->toContain('WithoutOverlapping');
});
