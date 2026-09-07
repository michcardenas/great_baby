<?php

namespace App\Modules\Dropi\Filament\Resources\ProductoResource\Pages;

use App\Modules\Dropi\Filament\Resources\ProductoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductos extends ListRecords
{
    protected static string $resource = ProductoResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
