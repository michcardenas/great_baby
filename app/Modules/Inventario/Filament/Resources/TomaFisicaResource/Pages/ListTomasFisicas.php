<?php

namespace App\Modules\Inventario\Filament\Resources\TomaFisicaResource\Pages;

use App\Modules\Inventario\Filament\Resources\TomaFisicaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTomasFisicas extends ListRecords
{
    protected static string $resource = TomaFisicaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
