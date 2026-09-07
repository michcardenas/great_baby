<?php

namespace App\Modules\Compras\Filament\Resources\OrdenCompraResource\Pages;

use App\Modules\Compras\Filament\Resources\OrdenCompraResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrdenesCompra extends ListRecords
{
    protected static string $resource = OrdenCompraResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
