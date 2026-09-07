<?php

namespace App\Modules\Compras\Filament\Resources\ImportacionResource\Pages;

use App\Modules\Compras\Filament\Resources\ImportacionResource;
use App\Modules\Compras\Models\Importacion;
use Filament\Resources\Pages\CreateRecord;

class CreateImportacion extends CreateRecord
{
    protected static string $resource = ImportacionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['numero'] = Importacion::siguienteNumero();
        $data['creado_por'] = auth()->id();
        $data['estado'] = $data['estado'] ?? 'en_transito';

        return $data;
    }
}
