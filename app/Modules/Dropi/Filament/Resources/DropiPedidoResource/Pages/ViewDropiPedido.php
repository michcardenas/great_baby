<?php

namespace App\Modules\Dropi\Filament\Resources\DropiPedidoResource\Pages;

use App\Modules\Dropi\Filament\Resources\DropiPedidoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDropiPedido extends ViewRecord
{
    protected static string $resource = DropiPedidoResource::class;

    protected string $view = 'dropi.pages.view-pedido';

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
