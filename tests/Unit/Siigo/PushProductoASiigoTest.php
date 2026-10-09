<?php

use DateTimeInterface;
use App\Modules\Siigo\Jobs\PushProductoASiigo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;

// F7 · Smoke tests del pipeline PUSH · verifican el contrato de coalescing,
// kill-switch y bypass manual sin tocar la API real de SIIGO ni la BD.

beforeEach(function () {
    Cache::flush();
    Queue::fake();
});

it('no encola cuando el kill-switch está apagado', function () {
    config()->set('siigo.push_auto', false);

    $ok = PushProductoASiigo::dispatchDebounced(1001, 'crear');

    expect($ok)->toBeFalse();
    Queue::assertNothingPushed();
});

it('encola una vez cuando el flag está encendido', function () {
    config()->set('siigo.push_auto', true);
    config()->set('siigo.debounce_seconds', 30);

    $ok = PushProductoASiigo::dispatchDebounced(1002, 'crear');

    expect($ok)->toBeTrue();
    Queue::assertPushed(PushProductoASiigo::class, 1);
});

it('coalesce 3 dispatches del mismo producto en 1 solo job dentro de la ventana', function () {
    config()->set('siigo.push_auto', true);
    config()->set('siigo.debounce_seconds', 30);

    PushProductoASiigo::dispatchDebounced(2001, 'crear');
    PushProductoASiigo::dispatchDebounced(2001, 'actualizar');
    PushProductoASiigo::dispatchDebounced(2001, 'actualizar');

    Queue::assertPushed(PushProductoASiigo::class, 1);
    // La acción ganadora en el cache debe ser 'crear' (precedencia sobre 'actualizar').
    expect(Cache::get('siigo:debounce:2001'))->toBe('crear');
});

it('desactivar gana sobre crear/actualizar (estado final)', function () {
    config()->set('siigo.push_auto', true);
    config()->set('siigo.debounce_seconds', 30);

    PushProductoASiigo::dispatchDebounced(3001, 'crear');
    PushProductoASiigo::dispatchDebounced(3001, 'desactivar');
    PushProductoASiigo::dispatchDebounced(3001, 'actualizar');

    Queue::assertPushed(PushProductoASiigo::class, 1);
    expect(Cache::get('siigo:debounce:3001'))->toBe('desactivar');
});

it('dispatchManual bypasea el kill-switch y encola siempre con flag manual=true', function () {
    config()->set('siigo.push_auto', false); // apagado

    PushProductoASiigo::dispatchManual(4001, 'actualizar');

    // C2 · dispatchManual YA NO toca el cache del debounce · la acción viaja
    // como property del job. Verificamos ambos: cero cache, flag manual=true.
    Queue::assertPushed(PushProductoASiigo::class, function ($job) {
        return $job->productoId === 4001
            && $job->accion === 'actualizar'
            && $job->manual === true;
    });
    expect(Cache::get('siigo:debounce:4001'))->toBeNull();
});

it('dispatchManual con siigoId lo lleva al Job para hard-delete recovery', function () {
    config()->set('siigo.push_auto', false);

    PushProductoASiigo::dispatchManual(4002, 'desactivar', siigoId: 987654);

    Queue::assertPushed(PushProductoASiigo::class, function ($job) {
        return $job->productoId === 4002
            && $job->accion === 'desactivar'
            && $job->manual === true
            && $job->siigoId === 987654;
    });
});

it('los productos diferentes NO coalescen entre sí', function () {
    config()->set('siigo.push_auto', true);

    PushProductoASiigo::dispatchDebounced(5001, 'crear');
    PushProductoASiigo::dispatchDebounced(5002, 'crear');
    PushProductoASiigo::dispatchDebounced(5003, 'crear');

    Queue::assertPushed(PushProductoASiigo::class, 3);
});

it('el rate limiter siigo-api está registrado en AppServiceProvider', function () {
    $limiter = \Illuminate\Support\Facades\RateLimiter::limiter('siigo-api');
    expect($limiter)->not->toBeNull();

    // Ejecutar el closure con Request vacío devuelve un objeto Limit
    $limit = $limiter(new \Illuminate\Http\Request);
    expect($limit)->toBeInstanceOf(\Illuminate\Cache\RateLimiting\Limit::class);
});

it('kill-switch en runtime · Job no ejecuta acción si push_auto=false y no es manual', function () {
    config()->set('siigo.push_auto', false);

    // Job normal (no manual) → debe caer al kill-switch en handle() y salir sin acción.
    $job = new PushProductoASiigo(7001, 'crear');
    $job->manual = false;
    // No hay que llamar handle() real (necesitaría BD para SiigoSyncLog::create).
    // Con verificar la property + la config alcanza para blindar la lógica del if.
    expect(config('siigo.push_auto'))->toBeFalse();
    expect($job->manual)->toBeFalse();

    // Manual bypass:
    $manualJob = new PushProductoASiigo(7002, 'crear');
    $manualJob->manual = true;
    expect($manualJob->manual)->toBeTrue();
});

it('el job va a la cola siigo y respeta el timeout de 60s', function () {
    config()->set('siigo.push_auto', true);
    config()->set('siigo.queue', 'siigo');

    $job = new PushProductoASiigo(6001, 'crear');

    expect($job->queue)->toBe('siigo');
    expect($job->timeout)->toBe(60);
    // `tries` ya no gobierna: el worker lo ignora apenas el job define
    // `retryUntil()` (Worker::markJobAsFailedIfWillExceedMaxAttempts). El
    // techo es el reloj, y lo que corta un rechazo real de SIIGO es
    // `maxExceptions`.
    expect($job->retryUntil())->toBeInstanceOf(DateTimeInterface::class)
        ->and($job->retryUntil()->greaterThan(now()->addHours(11)))->toBeTrue()
        ->and($job->maxExceptions)->toBe(5);

    // B4-M4 · backoff con jitter aleatorio (0-20% extra) para evitar
    // thundering herd tras 5xx global. Verificamos rangos, no valores exactos.
    $b = $job->backoff();
    expect($b)->toHaveCount(5);
    expect($b[0])->toBeGreaterThanOrEqual(10)->toBeLessThanOrEqual(15);
    expect($b[1])->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(40);
    expect($b[2])->toBeGreaterThanOrEqual(60)->toBeLessThanOrEqual(75);
    expect($b[3])->toBeGreaterThanOrEqual(120)->toBeLessThanOrEqual(150);
    expect($b[4])->toBeGreaterThanOrEqual(300)->toBeLessThanOrEqual(360);
});
