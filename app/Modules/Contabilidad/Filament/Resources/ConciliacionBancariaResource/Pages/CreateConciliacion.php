<?php

namespace App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConciliacion extends CreateRecord
{
    protected static string $resource = ConciliacionBancariaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] ??= auth()->id();

        return $data;
    }
}
