<?php

namespace App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource\Pages;

use App\Modules\Contabilidad\Filament\Resources\GastoOperativoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGastos extends ListRecords
{
    protected static string $resource = GastoOperativoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
