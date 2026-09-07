<?php

namespace App\Modules\Cartera\Filament\Resources\FacturaVentaResource\Pages;

use App\Modules\Cartera\Filament\Resources\FacturaVentaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFacturaVenta extends CreateRecord
{
    protected static string $resource = FacturaVentaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['saldo'] = $data['total'] ?? 0;
        $data['estado'] = 'pendiente';
        return $data;
    }
}
