<?php

namespace App\Modules\Cartera\Filament\Resources\CobranzaRegistroResource\Pages;

use App\Modules\Cartera\Filament\Resources\CobranzaRegistroResource;
use App\Modules\Cartera\Services\CobranzaWhatsApp;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListCobranzas extends ListRecords
{
    protected static string $resource = CobranzaRegistroResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('barrer')
                ->label('Ejecutar barrido de cobranza')
                ->icon('heroicon-o-play')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Envía WhatsApp a todos los clientes con facturas vencidas según su tramo de antigüedad. No duplica mensajes recientes.')
                ->action(function () {
                    $r = app(CobranzaWhatsApp::class)->ejecutarBarrido();
                    Notification::make()
                        ->title('Barrido completado')
                        ->body("Procesadas: {$r['procesadas']} · Enviadas: {$r['enviadas']} · Sin tel: {$r['sin_telefono']} · Escaladas: {$r['escaladas']}")
                        ->success()->send();
                }),
        ];
    }
}
