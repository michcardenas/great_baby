<?php

namespace App\Modules\Compras\Filament\Resources\ImportacionResource\Pages;

use App\Modules\Compras\Filament\Resources\ImportacionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditImportacion extends EditRecord
{
    protected static string $resource = ImportacionResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
