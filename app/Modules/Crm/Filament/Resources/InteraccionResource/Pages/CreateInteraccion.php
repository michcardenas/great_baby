<?php

namespace App\Modules\Crm\Filament\Resources\InteraccionResource\Pages;

use App\Modules\Crm\Filament\Resources\InteraccionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInteraccion extends CreateRecord
{
    protected static string $resource = InteraccionResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();
        return $data;
    }
}
