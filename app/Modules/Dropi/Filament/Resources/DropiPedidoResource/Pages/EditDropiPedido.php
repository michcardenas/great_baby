<?php

namespace App\Modules\Dropi\Filament\Resources\DropiPedidoResource\Pages;

use App\Modules\Dropi\Filament\Resources\DropiPedidoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDropiPedido extends EditRecord
{
    protected static string $resource = DropiPedidoResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
