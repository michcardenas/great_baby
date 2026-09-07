<?php

namespace App\Modules\Cartera\Filament\Resources\FacturaVentaResource\Pages;

use App\Modules\Cartera\Filament\Resources\FacturaVentaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFacturasVenta extends ListRecords
{
    protected static string $resource = FacturaVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
