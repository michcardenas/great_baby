<?php

namespace App\Modules\Cartera\Filament\Resources\FacturaVentaResource\Pages;

use App\Modules\Cartera\Filament\Resources\FacturaVentaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFacturaVenta extends ViewRecord
{
    protected static string $resource = FacturaVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
