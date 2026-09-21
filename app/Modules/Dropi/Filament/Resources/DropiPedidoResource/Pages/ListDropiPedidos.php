<?php

namespace App\Modules\Dropi\Filament\Resources\DropiPedidoResource\Pages;

use App\Modules\Dropi\Actions\SincronizarPedidosDropi;
use App\Modules\Dropi\Filament\Resources\DropiPedidoResource;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\RateLimiter;

class ListDropiPedidos extends ListRecords
{
    protected static string $resource = DropiPedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sincronizar')
                ->label('Sincronizar con Dropi')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                // Deshabilitado por ahora: la conexión directa (API/MCP) con Dropi
                // aún no está habilitada. Los pedidos entran por "Importar pedidos
                // (Excel)". Se re-activa quitando ->disabled() cuando llegue el token.
                ->disabled()
                ->tooltip('Disponible cuando se active la conexión directa con Dropi (API/MCP). Por ahora usa "Importar pedidos (Excel)".')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar pedidos Dropi')
                ->modalDescription('Trae pedidos nuevos o actualizados desde Dropi. Idempotente: no duplica guías.')
                ->action(function () {
                    $key = 'dropi-sync:' . auth()->id();
                    if (RateLimiter::tooManyAttempts($key, 3)) {
                        Notification::make()
                            ->title('Espera un momento')
                            ->body('Ya sincronizaste hace poco. Reintenta en ' . RateLimiter::availableIn($key) . 's.')
                            ->warning()->send();
                        return;
                    }
                    RateLimiter::hit($key, 60);

                    $r = app(SincronizarPedidosDropi::class)->handle(CarbonImmutable::now()->subDays(7));

                    Notification::make()
                        ->title('Sincronización completada')
                        ->body("Procesados: {$r['procesados']} · Nuevos: {$r['nuevos']} · Actualizados: {$r['actualizados']} · Pendientes por inventario: {$r['pendientes_inv']}")
                        ->success()
                        ->send();
                }),
            // "Crear Pedido" removido: los pedidos entran solo por Excel (ver canCreate()).
        ];
    }
}
