<?php

namespace App\Modules\Dropi\Filament\Resources\InventarioUbicacionResource\Pages;

use App\Modules\Dropi\Filament\Resources\InventarioUbicacionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventarioUbicacion extends EditRecord
{
    protected static string $resource = InventarioUbicacionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
