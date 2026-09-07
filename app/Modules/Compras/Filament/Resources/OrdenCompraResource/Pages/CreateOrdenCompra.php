<?php

namespace App\Modules\Compras\Filament\Resources\OrdenCompraResource\Pages;

use App\Modules\Compras\Actions\RecalcularTotalesOC;
use App\Modules\Compras\Filament\Resources\OrdenCompraResource;
use App\Modules\Compras\Models\OrdenCompra;
use Filament\Resources\Pages\CreateRecord;

class CreateOrdenCompra extends CreateRecord
{
    protected static string $resource = OrdenCompraResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['numero'] = OrdenCompra::siguienteNumero();
        $data['fecha_emision'] = $data['fecha_emision'] ?? now();
        $data['creado_por'] = auth()->id();
        $data['estado'] = 'borrador';

        return $data;
    }

    protected function afterCreate(): void
    {
        RecalcularTotalesOC::run($this->record);
    }
}
