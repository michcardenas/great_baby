<?php

use App\Modules\Dropi\Actions\SincronizarPedidosDropi;
use App\Modules\Dropi\Clients\DropiClientInterface;
use App\Modules\Dropi\Clients\DropiMockClient;
use App\Modules\Dropi\Enums\EstadoPedidoDropi;
use App\Modules\Dropi\Models\DropiPedido;
use Carbon\CarbonImmutable;

beforeEach(function () {
    // Reforzamos que el driver activo sea el mock aunque .env cambie
    app()->bind(
        DropiClientInterface::class,
        fn () => new DropiMockClient(storage_path('app/dropi-fixtures')),
    );
});

it('sincroniza pedidos desde el fixture', function () {
    $result = app(SincronizarPedidosDropi::class)->handle(CarbonImmutable::now()->subYear());

    expect($result['driver'])->toBe('mock')
        ->and($result['procesados'])->toBe(4)
        ->and($result['nuevos'])->toBe(4);

    expect(DropiPedido::where('guia', 'GUI-100001')->exists())->toBeTrue();
});

it('es idempotente: correr dos veces no duplica', function () {
    $action = app(SincronizarPedidosDropi::class);
    $desde = CarbonImmutable::now()->subYear();

    $primera = $action->handle($desde);
    $segunda = $action->handle($desde);

    expect($primera['nuevos'])->toBe(4)
        ->and($segunda['nuevos'])->toBe(0)
        ->and($segunda['actualizados'])->toBe(4)
        ->and(DropiPedido::count())->toBe(4);
});

it('marca los pedidos como pendiente_inventario cuando no hay stock', function () {
    app(SincronizarPedidosDropi::class)->handle(CarbonImmutable::now()->subYear());

    $pedido = DropiPedido::where('guia', 'GUI-100001')->first();

    expect($pedido->estado)->toBe(EstadoPedidoDropi::PendienteInventario);
});
