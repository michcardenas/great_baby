<?php

namespace App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource\Pages;

use App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAlertaStock extends EditRecord
{
    protected static string $resource = AlertaStockConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
