<?php

namespace App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\ConciliacionBancariaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditConciliacion extends EditRecord
{
    protected static string $resource = ConciliacionBancariaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
