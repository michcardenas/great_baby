<?php

namespace App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGasto extends CreateRecord
{
    protected static string $resource = GastoOperativoResource::class;

    /**
     * `solicita_id` es obligatorio (quién registra el gasto). Lo tomamos del usuario en sesión.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['solicita_id'] ??= auth()->id();

        return $data;
    }
}
