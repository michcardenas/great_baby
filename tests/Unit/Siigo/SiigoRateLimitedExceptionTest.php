<?php

use App\Modules\Siigo\Exceptions\SiigoRateLimitedException;

// B-TESTS · verifica la excepción tipada del cliente en 429 (B3-M1).

it('lleva el retryAfter para que el Job haga release', function () {
    $e = new SiigoRateLimitedException(retryAfter: 42, path: '/v1/products');
    expect($e->retryAfter)->toBe(42);
    expect($e->getMessage())->toContain('/v1/products');
    expect($e->getMessage())->toContain('42s');
});

it('extiende RuntimeException para no ser silenciada por catch (\\Throwable) genéricos débiles', function () {
    $e = new SiigoRateLimitedException(retryAfter: 10);
    expect($e)->toBeInstanceOf(\RuntimeException::class);
});
