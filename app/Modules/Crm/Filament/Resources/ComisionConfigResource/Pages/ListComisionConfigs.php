<?php

namespace App\Modules\Crm\Filament\Resources\ComisionConfigResource\Pages;

use App\Modules\Crm\Filament\Resources\ComisionConfigResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListComisionConfigs extends ListRecords
{
    protected static string $resource = ComisionConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
