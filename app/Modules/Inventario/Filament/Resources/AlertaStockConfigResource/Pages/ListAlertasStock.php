<?php

namespace App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource\Pages;

use App\Modules\Inventario\Filament\Resources\AlertaStockConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAlertasStock extends ListRecords
{
    protected static string $resource = AlertaStockConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
