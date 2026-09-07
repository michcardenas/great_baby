<?php

namespace App\Modules\Dropi\Filament\Resources\DropiCorteResource\Pages;

use App\Modules\Dropi\Filament\Resources\DropiCorteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDropiCortes extends ListRecords
{
    protected static string $resource = DropiCorteResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
