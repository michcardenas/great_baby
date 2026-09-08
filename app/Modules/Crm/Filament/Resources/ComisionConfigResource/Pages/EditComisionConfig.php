<?php

namespace App\Modules\Crm\Filament\Resources\ComisionConfigResource\Pages;

use App\Modules\Crm\Filament\Resources\ComisionConfigResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditComisionConfig extends EditRecord
{
    protected static string $resource = ComisionConfigResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
