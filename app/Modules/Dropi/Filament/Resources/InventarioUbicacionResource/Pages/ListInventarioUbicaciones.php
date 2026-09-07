<?php

namespace App\Modules\Dropi\Filament\Resources\InventarioUbicacionResource\Pages;

use App\Modules\Dropi\Filament\Resources\InventarioUbicacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventarioUbicaciones extends ListRecords
{
    protected static string $resource = InventarioUbicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
