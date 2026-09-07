<?php

namespace App\Modules\Inventario\Filament\Resources\TrasladoResource\Pages;

use App\Modules\Inventario\Filament\Resources\TrasladoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTraslados extends ListRecords
{
    protected static string $resource = TrasladoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
