<?php

namespace App\Modules\Compras\Filament\Resources\ImportacionResource\Pages;

use App\Modules\Compras\Filament\Resources\ImportacionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListImportaciones extends ListRecords
{
    protected static string $resource = ImportacionResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
