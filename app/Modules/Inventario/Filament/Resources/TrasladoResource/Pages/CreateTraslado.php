<?php

namespace App\Modules\Inventario\Filament\Resources\TrasladoResource\Pages;

use App\Modules\Inventario\Filament\Resources\TrasladoResource;
use App\Modules\Inventario\Models\Traslado;
use Filament\Resources\Pages\CreateRecord;

class CreateTraslado extends CreateRecord
{
    protected static string $resource = TrasladoResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['numero'] = Traslado::siguienteNumero();
        $data['solicitado_por'] = auth()->id();
        $data['estado'] = 'borrador';

        return $data;
    }
}
