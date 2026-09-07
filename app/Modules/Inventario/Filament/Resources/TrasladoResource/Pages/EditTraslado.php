<?php

namespace App\Modules\Inventario\Filament\Resources\TrasladoResource\Pages;

use App\Modules\Inventario\Filament\Resources\TrasladoResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTraslado extends EditRecord
{
    protected static string $resource = TrasladoResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
