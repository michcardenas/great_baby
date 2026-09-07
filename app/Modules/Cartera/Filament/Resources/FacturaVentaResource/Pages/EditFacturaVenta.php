<?php

namespace App\Modules\Cartera\Filament\Resources\FacturaVentaResource\Pages;

use App\Modules\Cartera\Filament\Resources\FacturaVentaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFacturaVenta extends EditRecord
{
    protected static string $resource = FacturaVentaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
