<?php

namespace App\Modules\Inventario\Filament\Resources\TomaFisicaResource\Pages;

use App\Modules\Inventario\Filament\Resources\TomaFisicaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTomaFisica extends EditRecord
{
    protected static string $resource = TomaFisicaResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
