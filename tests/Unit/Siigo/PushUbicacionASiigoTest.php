<?php

use App\Modules\Siigo\Jobs\PushUbicacionASiigo;
use Illuminate\Support\Facades\Queue;

// UBIC-9 · Smoke tests del Job de push de ubicaciones a SIIGO como warehouse.
// Mismo patrón que PushRecepcionASiigo/PushProductoASiigo (Unit sin BD, Queue::fake).

beforeEach(function () {
    Queue::fake();
});

it('kill-switch · con push_auto=false y manual=false, el flag queda en false', function () {
    config()->set('siigo.push_auto', false);
    $job = new PushUbicacionASiigo(ubicacionId: 101);
    expect($job->manual)->toBeFalse();
    expect(config('siigo.push_auto'))->toBeFalse();
});

it('dispatchManual · marca manual=true y bypasea el kill-switch', function () {
    config()->set('siigo.push_auto', false);
    PushUbicacionASiigo::dispatchManual(id: 202);
    Queue::assertPushed(PushUbicacionASiigo::class, function ($job) {
        return $job->ubicacionId === 202 && $job->manual === true;
    });
});

it('cola correcta (siigo) y timeouts/retries estándar', function () {
    config()->set('siigo.queue', 'siigo');
    $job = new PushUbicacionASiigo(ubicacionId: 303);
    expect($job->queue)->toBe('siigo');
    expect($job->timeout)->toBe(60);
    expect($job->tries)->toBe(5);
});

it('backoff con jitter (progresión 10 · 30 · 60 · 120 · 300)', function () {
    $job = new PushUbicacionASiigo(ubicacionId: 404);
    $b = $job->backoff();
    expect($b)->toHaveCount(5);
    // Progresión creciente (sin jitter porque es fijo en este Job)
    expect($b[0])->toBe(10);
    expect($b[1])->toBe(30);
    expect($b[2])->toBe(60);
    expect($b[3])->toBe(120);
    expect($b[4])->toBe(300);
});

it('middleware() devuelve RateLimited + WithoutOverlapping con lock específico', function () {
    $job = new PushUbicacionASiigo(ubicacionId: 505);
    $middlewares = collect($job->middleware())->map(fn ($m) => class_basename($m))->all();
    expect($middlewares)->toContain('RateLimited');
    expect($middlewares)->toContain('WithoutOverlapping');
});

it('dispatch normal · propiedad manual queda false (no bypasea kill-switch)', function () {
    PushUbicacionASiigo::dispatch(606);
    Queue::assertPushed(PushUbicacionASiigo::class, function ($job) {
        return $job->ubicacionId === 606 && $job->manual === false;
    });
});
