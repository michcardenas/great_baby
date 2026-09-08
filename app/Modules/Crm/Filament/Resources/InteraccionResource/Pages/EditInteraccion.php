<?php

namespace App\Modules\Crm\Filament\Resources\InteraccionResource\Pages;

use App\Modules\Crm\Filament\Resources\InteraccionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInteraccion extends EditRecord
{
    protected static string $resource = InteraccionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
