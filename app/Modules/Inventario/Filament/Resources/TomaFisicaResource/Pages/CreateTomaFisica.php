<?php

namespace App\Modules\Inventario\Filament\Resources\TomaFisicaResource\Pages;

use App\Modules\Inventario\Filament\Resources\TomaFisicaResource;
use App\Modules\Inventario\Models\TomaFisica;
use Filament\Resources\Pages\CreateRecord;

class CreateTomaFisica extends CreateRecord
{
    protected static string $resource = TomaFisicaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['numero'] = TomaFisica::siguienteNumero();
        $data['creada_por'] = auth()->id();
        $data['estado'] = 'borrador';

        return $data;
    }
}
