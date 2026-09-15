<?php

namespace App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGasto extends EditRecord
{
    protected static string $resource = GastoOperativoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
